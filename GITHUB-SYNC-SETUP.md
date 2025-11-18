# GitHub Sync Setup Guide for MacBook Air

## Current Status
✅ Your directory is already a git repository  
✅ Remote is configured: `https://github.com/misterlinderman/twintack.git`  
✅ Multiple branches are configured (main, feature branches)  
✅ `.gitignore` is properly set up

## Step 1: Install Xcode Command Line Tools
1. Complete the installation dialog that just appeared
2. Wait for installation to finish (may take 10-15 minutes)
3. Verify installation by running: `git --version`

## Step 2: Check Current Status
Once git is working, run these commands to see what needs to be synced:

```bash
cd "/Users/mattlinder/Dropbox/Development/Websites/TwinTack/Wordpress Files"
git status
git branch
git log --oneline -10
```

## Step 3: Sync Your Changes to GitHub

### Option A: If you're on the main branch and want to push all changes
```bash
# Check what branch you're on
git branch

# See what files have changed
git status

# Add all changes (respects .gitignore)
git add .

# Commit the changes
git commit -m "Sync latest changes from Dropbox"

# Push to GitHub
git push origin main
```

### Option B: If you're on a feature branch
```bash
# Check current branch
git branch

# Add and commit changes
git add .
git commit -m "Update feature branch with latest changes"

# Push to the current branch
git push origin $(git branch --show-current)
```

### Option C: If you want to merge changes from a feature branch to main first
```bash
# Switch to main branch
git checkout main

# Pull latest from GitHub (in case there are remote changes)
git pull origin main

# Merge your feature branch
git merge feature/your-branch-name

# Push to GitHub
git push origin main
```

## Important Considerations

### Using Dropbox with Git
⚠️ **Dropbox + Git can work, but be aware:**

1. **File Conflicts**: If you work on the same files from multiple machines, Dropbox can cause conflicts
2. **Git Metadata**: Dropbox syncs `.git` folder, which is generally fine but can occasionally cause issues
3. **Best Practice**: Consider using git for version control and Dropbox as a backup, but rely on git for syncing between machines

### Recommended Workflow
1. **Primary Sync Method**: Use `git pull` and `git push` to sync between machines
2. **Dropbox as Backup**: Keep Dropbox sync as a backup, but don't rely on it for version control
3. **Before Working**: Always `git pull` to get latest changes
4. **After Working**: Always `git commit` and `git push` your changes

### Alternative Setup (Recommended for Multi-Machine Development)
If you want a cleaner setup, consider:

1. **Clone fresh on MacBook Air**:
   ```bash
   cd ~/Development
   git clone https://github.com/misterlinderman/twintack.git
   ```

2. **Work directly from the cloned directory** (not Dropbox)

3. **Use git for syncing** between PC and MacBook Air

This avoids potential Dropbox conflicts and is the standard workflow for multi-machine development.

## Troubleshooting

### If you get merge conflicts:
```bash
# See conflicted files
git status

# Resolve conflicts in your editor, then:
git add .
git commit -m "Resolve merge conflicts"
git push origin main
```

### If you need to see what's different:
```bash
# Compare local vs remote
git fetch origin
git diff main origin/main

# See commit history
git log --oneline --graph --all
```

### If you want to discard local changes and match GitHub:
```bash
# WARNING: This will discard local changes!
git fetch origin
git reset --hard origin/main
```

## Next Steps After Installation

Once Xcode Command Line Tools are installed, I can help you:
1. Check the current git status
2. Review what files need to be committed
3. Safely push your changes to GitHub
4. Set up the optimal workflow for your MacBook Air

Just let me know when the installation is complete!

