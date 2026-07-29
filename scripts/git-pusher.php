<?php
/**
 * Smart Referral Pro - Git Version Control Pusher
 * 
 * A CLI tool for managing plugin versioning, tagging, and deployment.
 * Usage: php scripts/git-pusher.php [command] [options]
 * 
 * Commands:
 *   version [patch|minor|major]  - Bump version number
 *   tag                          - Create git tag from current version
 *   push [origin|staging|prod]   - Push to remote with optional deployment
 *   status                       - Show current version and git status
 *   changelog                    - Generate changelog from commits
 *   deploy [staging|production]  - Deploy to server via SSH
 *   rollback [version]           - Rollback to previous version
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

class SRP_GitPusher {

    private $plugin_file;
    private $plugin_dir;
    private $current_version;
    private $dry_run = false;
    private $verbose = false;

    // Color codes for terminal output
    const COLOR_RESET = "\033[0m";
    const COLOR_GREEN = "\033[32m";
    const COLOR_YELLOW = "\033[33m";
    const COLOR_RED = "\033[31m";
    const COLOR_BLUE = "\033[34m";
    const COLOR_CYAN = "\033[36m";

    public function __construct() {
        $this->plugin_dir = dirname(__DIR__);
        $this->plugin_file = $this->plugin_dir . '/smart-referral-pro.php';

        if (!file_exists($this->plugin_file)) {
            $this->error("Plugin file not found: {$this->plugin_file}");
            exit(1);
        }

        $this->current_version = $this->get_current_version();
    }

    /**
     * Main entry point
     */
    public function run($args) {
        if (empty($args[1])) {
            $this->show_help();
            return;
        }

        $command = $args[1];

        // Parse flags
        for ($i = 2; $i < count($args); $i++) {
            if ($args[$i] === '--dry-run') {
                $this->dry_run = true;
            }
            if ($args[$i] === '--verbose' || $args[$i] === '-v') {
                $this->verbose = true;
            }
        }

        switch ($command) {
            case 'version':
            case 'bump':
                $type = $args[2] ?? 'patch';
                $this->bump_version($type);
                break;

            case 'tag':
                $this->create_tag();
                break;

            case 'push':
                $remote = $args[2] ?? 'origin';
                $this->push($remote);
                break;

            case 'status':
                $this->show_status();
                break;

            case 'changelog':
                $this->generate_changelog();
                break;

            case 'deploy':
                $environment = $args[2] ?? 'staging';
                $this->deploy($environment);
                break;

            case 'rollback':
                $version = $args[2] ?? null;
                $this->rollback($version);
                break;

            case 'release':
                $type = $args[2] ?? 'patch';
                $this->full_release($type);
                break;

            case 'setup':
                $this->setup_hooks();
                break;

            case 'validate':
                $this->validate_plugin();
                break;

            default:
                $this->error("Unknown command: $command");
                $this->show_help();
                break;
        }
    }

    /**
     * Get current version from plugin file
     */
    private function get_current_version() {
        $content = file_get_contents($this->plugin_file);

        if (preg_match("/define\('SRP_VERSION',\s*'([^']+)'\)/", $content, $matches)) {
            return $matches[1];
        }

        if (preg_match('/Version:\s*([\d.]+)/', $content, $matches)) {
            return $matches[1];
        }

        return '1.0.0';
    }

    /**
     * Bump version number
     */
    private function bump_version($type = 'patch') {
        $this->info("Current version: {$this->current_version}");

        $parts = explode('.', $this->current_version);
        $major = (int)($parts[0] ?? 0);
        $minor = (int)($parts[1] ?? 0);
        $patch = (int)($parts[2] ?? 0);

        switch ($type) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
            default:
                $patch++;
                break;
        }

        $new_version = "$major.$minor.$patch";

        $this->info("Bumping to version: $new_version");

        if ($this->dry_run) {
            $this->warning("[DRY RUN] Would update version to $new_version");
            return;
        }

        // Update main plugin file
        $content = file_get_contents($this->plugin_file);
        $content = preg_replace(
            "/define\('SRP_VERSION',\s*'[^']+'\)/",
            "define('SRP_VERSION', '$new_version')",
            $content
        );
        $content = preg_replace(
            '/Version:\s*[\d.]+/',
            "Version: $new_version",
            $content
        );
        file_put_contents($this->plugin_file, $content);

        // Update all PHP files
        $this->update_version_in_files($new_version);

        // Update readme if exists
        $this->update_readme_version($new_version);

        $this->success("Version bumped to $new_version");

        // Git commit
        $this->run_git_command("add -A");
        $this->run_git_command("commit -m \"chore(release): bump version to $new_version\"");

        $this->current_version = $new_version;
    }

    /**
     * Update version in all PHP files
     */
    private function update_version_in_files($version) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->plugin_dir . '/includes')
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                $updated = preg_replace(
                    '/@version\s*[\d.]+/',
                    "@version $version",
                    $content
                );
                if ($content !== $updated) {
                    file_put_contents($file->getPathname(), $updated);
                    if ($this->verbose) {
                        $this->info("Updated: " . $file->getFilename());
                    }
                }
            }
        }
    }

    /**
     * Update readme version
     */
    private function update_readme_version($version) {
        $readme_files = [
            $this->plugin_dir . '/readme.txt',
            $this->plugin_dir . '/README.md'
        ];

        foreach ($readme_files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $content = preg_replace('/Stable tag:\s*[\d.]+/', "Stable tag: $version", $content);
                file_put_contents($file, $content);
            }
        }
    }

    /**
     * Create git tag
     */
    private function create_tag() {
        $tag = "v{$this->current_version}";

        // Check if tag already exists
        $existing = $this->run_git_command("tag -l $tag", true);
        if (trim($existing) === $tag) {
            $this->warning("Tag $tag already exists!");
            $this->info("Use 'git tag -d $tag' to delete it first.");
            return;
        }

        $this->info("Creating tag: $tag");

        if ($this->dry_run) {
            $this->warning("[DRY RUN] Would create tag $tag");
            return;
        }

        // Generate changelog for tag message
        $changelog = $this->get_changelog_since_last_tag();

        $this->run_git_command("tag -a $tag -m \"Release $tag\\n\\n$changelog\"");
        $this->success("Tag $tag created successfully!");
    }

    /**
     * Push to remote
     */
    private function push($remote = 'origin') {
        $branch = $this->run_git_command("branch --show-current", true);
        $branch = trim($branch);

        $this->info("Pushing to $remote/$branch...");

        if ($this->dry_run) {
            $this->warning("[DRY RUN] Would push to $remote/$branch");
            return;
        }

        $this->run_git_command("push $remote $branch");

        // Also push tags
        $this->run_git_command("push $remote --tags");

        $this->success("Pushed to $remote/$branch with tags!");
    }

    /**
     * Show current status
     */
    private function show_status() {
        $this->info("=== Smart Referral Pro - Git Status ===");
        echo "\n";

        $this->info("Current Version: {$this->current_version}");

        $branch = $this->run_git_command("branch --show-current", true);
        $this->info("Current Branch: " . trim($branch));

        $last_commit = $this->run_git_command("log -1 --pretty=format:\"%h - %s (%ar)\"", true);
        $this->info("Last Commit: " . trim($last_commit));

        $uncommitted = $this->run_git_command("status --short", true);
        if (trim($uncommitted)) {
            $this->warning("Uncommitted changes:");
            echo $uncommitted . "\n";
        } else {
            $this->success("Working tree clean");
        }

        $tags = $this->run_git_command("tag -l \"v*\" --sort=-v:refname | head -5", true);
        if (trim($tags)) {
            $this->info("Recent Tags:");
            echo $tags . "\n";
        }
    }

    /**
     * Generate changelog
     */
    private function generate_changelog() {
        $this->info("Generating changelog...");

        $last_tag = $this->run_git_command("describe --tags --abbrev=0 2>/dev/null || echo \"\"", true);
        $last_tag = trim($last_tag);

        if ($last_tag) {
            $range = "$last_tag..HEAD";
        } else {
            $range = "HEAD";
        }

        $commits = $this->run_git_command("log $range --pretty=format:\"- %s (%h)\" --no-merges", true);

        $changelog = "## [{$this->current_version}] - " . date('Y-m-d') . "\n\n";
        $changelog .= "### Changes\n";
        $changelog .= $commits . "\n\n";

        echo $changelog;

        // Append to CHANGELOG.md
        $changelog_file = $this->plugin_dir . '/CHANGELOG.md';
        if (file_exists($changelog_file)) {
            $existing = file_get_contents($changelog_file);
            $new_content = $changelog . $existing;
            file_put_contents($changelog_file, $new_content);
            $this->success("CHANGELOG.md updated!");
        }
    }

    /**
     * Get changelog since last tag
     */
    private function get_changelog_since_last_tag() {
        $last_tag = $this->run_git_command("describe --tags --abbrev=0 2>/dev/null || echo \"\"", true);
        $last_tag = trim($last_tag);

        if ($last_tag) {
            $commits = $this->run_git_command("log $last_tag..HEAD --pretty=format:\"- %s\" --no-merges", true);
        } else {
            $commits = $this->run_git_command("log --pretty=format:\"- %s\" --no-merges -20", true);
        }

        return $commits ?: "No changes recorded.";
    }

    /**
     * Deploy to environment
     */
    private function deploy($environment = 'staging') {
        $config_file = $this->plugin_dir . '/deploy/config.json';

        if (!file_exists($config_file)) {
            $this->error("Deploy config not found: $config_file");
            $this->info("Create deploy/config.json with your server settings.");
            return;
        }

        $config = json_decode(file_get_contents($config_file), true);

        if (!isset($config[$environment])) {
            $this->error("Environment '$environment' not found in config");
            return;
        }

        $env_config = $config[$environment];

        $this->info("Deploying to $environment...");

        if ($this->dry_run) {
            $this->warning("[DRY RUN] Would deploy to $environment");
            return;
        }

        // Build deployment package
        $this->build_package();

        // Deploy via SSH/rsync
        $host = $env_config['host'] ?? '';
        $user = $env_config['user'] ?? '';
        $path = $env_config['path'] ?? '';
        $key = $env_config['ssh_key'] ?? '~/.ssh/id_rsa';

        if (!$host || !$user || !$path) {
            $this->error("Invalid deploy configuration for $environment");
            return;
        }

        $local_path = $this->plugin_dir . '/dist/smart-referral-pro/';
        $remote_path = "$user@$host:$path/wp-content/plugins/smart-referral-pro/";

        $command = "rsync -avz --delete -e \"ssh -i $key -o StrictHostKeyChecking=no\" ";
        $command .= "--exclude='.git' --exclude='node_modules' --exclude='vendor' ";
        $command .= "$local_path $remote_path";

        $this->info("Running: $command");
        passthru($command, $exit_code);

        if ($exit_code === 0) {
            $this->success("Deployed to $environment successfully!");

            // Run post-deploy commands
            if (isset($env_config['post_deploy'])) {
                foreach ($env_config['post_deploy'] as $cmd) {
                    $ssh_cmd = "ssh -i $key $user@$host \"$cmd\"";
                    $this->info("Post-deploy: $cmd");
                    passthru($ssh_cmd);
                }
            }
        } else {
            $this->error("Deployment failed with code: $exit_code");
        }
    }

    /**
     * Build deployment package
     */
    private function build_package() {
        $this->info("Building deployment package...");

        $dist_dir = $this->plugin_dir . '/dist';
        $package_dir = $dist_dir . '/smart-referral-pro';

        // Clean previous build
        if (is_dir($dist_dir)) {
            $this->recursive_rmdir($dist_dir);
        }

        mkdir($dist_dir, 0755, true);
        mkdir($package_dir, 0755, true);

        // Copy files
        $exclude = [
            '.git', '.github', 'node_modules', 'vendor',
            'tests', 'scripts', 'deploy', 'bin',
            '.gitignore', '.distignore', '.editorconfig',
            'composer.json', 'composer.lock',
            'package.json', 'package-lock.json',
            'phpunit.xml', 'phpcs.xml',
            'README.md', 'CHANGELOG.md', 'CONTRIBUTING.md',
            'LICENSE'
        ];

        $this->recursive_copy($this->plugin_dir, $package_dir, $exclude);

        $this->success("Package built at: $package_dir");
    }

    /**
     * Rollback to previous version
     */
    private function rollback($version = null) {
        if (!$version) {
            // Get previous tag
            $tags = $this->run_git_command("tag -l \"v*\" --sort=-v:refname | head -2", true);
            $tag_list = array_filter(explode("\n", trim($tags)));

            if (count($tag_list) < 2) {
                $this->error("Not enough tags to rollback");
                return;
            }

            $version = $tag_list[1]; // Second most recent
        }

        $this->warning("Rolling back to $version...");

        if ($this->dry_run) {
            $this->warning("[DRY RUN] Would rollback to $version");
            return;
        }

        $this->run_git_command("checkout $version");
        $this->success("Rolled back to $version");
        $this->warning("You are now in a detached HEAD state. Create a branch if needed.");
    }

    /**
     * Full release workflow
     */
    private function full_release($type = 'patch') {
        $this->info("=== Starting Full Release Workflow ===");
        echo "\n";

        // 1. Validate
        $this->validate_plugin();

        // 2. Bump version
        $this->bump_version($type);

        // 3. Generate changelog
        $this->generate_changelog();

        // 4. Commit changelog
        $this->run_git_command("add CHANGELOG.md");
        $this->run_git_command("commit --amend --no-edit");

        // 5. Create tag
        $this->create_tag();

        // 6. Push
        $this->push('origin');

        echo "\n";
        $this->success("=== Release {$this->current_version} Complete! ===");
        $this->info("GitHub Actions will now handle the rest.");
    }

    /**
     * Setup git hooks
     */
    private function setup_hooks() {
        $hooks_dir = $this->plugin_dir . '/.git/hooks';

        if (!is_dir($hooks_dir)) {
            $this->error("Not a git repository or .git/hooks not found");
            return;
        }

        // Pre-commit hook
        $pre_commit = '#!/bin/sh
# Smart Referral Pro - Pre-commit Hook
# Run validation before commit

echo "Running pre-commit validation..."

# Check PHP syntax
find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*" | while read file; do
    php -l "$file" > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "Syntax error in $file"
        exit 1
    fi
done

# Check for debug code
grep -r "var_dump\|print_r\|die(" . --include="*.php" --exclude-dir=vendor --exclude-dir=node_modules
if [ $? -eq 0 ]; then
    echo "WARNING: Debug code found!"
    # exit 1  # Uncomment to block commits with debug code
fi

echo "Pre-commit checks passed!"
';

        file_put_contents($hooks_dir . '/pre-commit', $pre_commit);
        chmod($hooks_dir . '/pre-commit', 0755);

        // Post-merge hook (for dependency updates)
        $post_merge = '#!/bin/sh
# Smart Referral Pro - Post-merge Hook

if [ -f composer.lock ]; then
    echo "Updating composer dependencies..."
    composer install --no-interaction
fi

if [ -f package-lock.json ]; then
    echo "Updating npm dependencies..."
    npm ci
fi
';

        file_put_contents($hooks_dir . '/post-merge', $post_merge);
        chmod($hooks_dir . '/post-merge', 0755);

        $this->success("Git hooks installed!");
    }

    /**
     * Validate plugin before release
     */
    private function validate_plugin() {
        $this->info("Validating plugin...");

        $errors = [];
        $warnings = [];

        // Check PHP syntax
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->plugin_dir)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $output = [];
                $return = 0;
                exec('php -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $return);

                if ($return !== 0) {
                    $errors[] = "Syntax error in " . $file->getFilename();
                }
            }
        }

        // Check for required files
        $required_files = [
            'smart-referral-pro.php',
            'includes/Core.php',
            'includes/Database.php',
            'includes/Security.php'
        ];

        foreach ($required_files as $file) {
            if (!file_exists($this->plugin_dir . '/' . $file)) {
                $errors[] = "Required file missing: $file";
            }
        }

        // Check for debug code
        $debug_patterns = ['var_dump(', 'print_r(', 'die(', 'exit('];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                foreach ($debug_patterns as $pattern) {
                    if (strpos($content, $pattern) !== false) {
                        $warnings[] = "Debug code found in " . $file->getFilename() . ": $pattern";
                    }
                }
            }
        }

        // Check version consistency
        $version_files = [
            'smart-referral-pro.php',
            'includes/Core.php'
        ];

        $versions = [];
        foreach ($version_files as $file) {
            $path = $this->plugin_dir . '/' . $file;
            if (file_exists($path)) {
                $content = file_get_contents($path);
                if (preg_match('/Version:\s*([\d.]+)/', $content, $matches)) {
                    $versions[$file] = $matches[1];
                }
            }
        }

        $unique_versions = array_unique($versions);
        if (count($unique_versions) > 1) {
            $errors[] = "Version mismatch: " . json_encode($versions);
        }

        // Report
        if (!empty($warnings)) {
            foreach ($warnings as $warning) {
                $this->warning("  $warning");
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->error("  $error");
            }
            exit(1);
        }

        $this->success("Plugin validation passed!");
    }

    /**
     * Run git command
     */
    private function run_git_command($command, $return_output = false) {
        $full_command = "cd " . escapeshellarg($this->plugin_dir) . " && git $command 2>&1";

        if ($this->verbose) {
            $this->info("Git: $command");
        }

        if ($return_output) {
            return shell_exec($full_command);
        }

        passthru($full_command, $exit_code);

        if ($exit_code !== 0) {
            $this->error("Git command failed: $command");
            exit($exit_code);
        }
    }

    /**
     * Recursive copy
     */
    private function recursive_copy($src, $dst, $exclude = []) {
        $dir = opendir($src);
        @mkdir($dst);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            if (in_array($file, $exclude)) continue;

            $src_file = $src . '/' . $file;
            $dst_file = $dst . '/' . $file;

            if (is_dir($src_file)) {
                $this->recursive_copy($src_file, $dst_file, $exclude);
            } else {
                copy($src_file, $dst_file);
            }
        }

        closedir($dir);
    }

    /**
     * Recursive remove directory
     */
    private function recursive_rmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->recursive_rmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Output helpers
     */
    private function info($message) {
        echo self::COLOR_BLUE . "  $message" . self::COLOR_RESET . "\n";
    }

    private function success($message) {
        echo self::COLOR_GREEN . "  $message" . self::COLOR_RESET . "\n";
    }

    private function warning($message) {
        echo self::COLOR_YELLOW . "  $message" . self::COLOR_RESET . "\n";
    }

    private function error($message) {
        echo self::COLOR_RED . "  $message" . self::COLOR_RESET . "\n";
    }

    /**
     * Show help
     */
    private function show_help() {
        echo <<<HELP
================================================================
     Smart Referral Pro - Git Version Control Pusher          
================================================================

Usage: php scripts/git-pusher.php [command] [options]

Commands:
  version [type]     Bump version (patch|minor|major)
  tag                Create git tag from current version
  push [remote]      Push to remote with tags
  status             Show version, branch, and git status
  changelog          Generate changelog from commits
  deploy [env]       Deploy to environment (staging|production)
  rollback [ver]     Rollback to previous version
  release [type]     Full release workflow (bump + tag + push)
  setup              Install git hooks
  validate           Validate plugin before release

Options:
  --dry-run          Show what would be done without executing
  --verbose, -v      Show detailed output

Examples:
  php scripts/git-pusher.php version minor
  php scripts/git-pusher.php release major --dry-run
  php scripts/git-pusher.php deploy staging
  php scripts/git-pusher.php rollback v1.0.0

HELP;
    }
}

// Run
$pusher = new SRP_GitPusher();
$pusher->run($argv);
