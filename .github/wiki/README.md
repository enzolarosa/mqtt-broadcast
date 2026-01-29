# GitHub Wiki Setup Guide

This directory contains the source files for the GitHub Wiki.

## How to Setup Wiki

### Option 1: Manual Setup (Easiest)

1. Go to https://github.com/enzolarosa/mqtt-broadcast/wiki
2. Click "Create the first page"
3. Click "Edit" on any page
4. Copy content from `.github/wiki/Home.md`
5. Save the page
6. Repeat for other pages

### Option 2: Git Clone (Advanced)

GitHub Wikis are separate Git repositories:

```bash
# Clone wiki repo
git clone https://github.com/enzolarosa/mqtt-broadcast.wiki.git

# Copy wiki files
cp .github/wiki/*.md mqtt-broadcast.wiki/

# Push to wiki
cd mqtt-broadcast.wiki
git add .
git commit -m "Initial wiki setup"
git push origin master
```

### Option 3: GitHub API

```bash
# You'll need a GitHub token with 'repo' scope

# Create Home page
curl -X PUT \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  -H "Content-Type: application/json" \
  https://api.github.com/repos/enzolarosa/mqtt-broadcast/pages \
  -d '{"page": "Home", "content": "..."}'
```

## Wiki Pages to Create

**Essential pages:**
- [x] `Home.md` - Main wiki page
- [x] `_Sidebar.md` - Sidebar navigation
- [ ] `Installation.md` - Installation guide
- [ ] `Quick-Start-Guide.md` - Quick start
- [ ] `Configuration.md` - Configuration reference
- [ ] `FAQ.md` - Frequently asked questions

**Advanced pages:**
- [ ] `Production-Deployment.md`
- [ ] `ESP32-Integration.md`
- [ ] `IoT-Temperature-Monitoring.md`
- [ ] `Troubleshooting.md`

## Wiki File Structure

```
mqtt-broadcast.wiki/
├── Home.md                      (Main page)
├── _Sidebar.md                  (Navigation sidebar)
├── Installation.md
├── Quick-Start-Guide.md
├── Configuration.md
├── Publishing-Messages.md
├── Event-Listeners.md
├── Multiple-Brokers.md
├── TLS-SSL-Security.md
├── Dashboard-Overview.md
├── Dashboard-Authentication.md
├── Production-Deployment.md
├── Performance-Tuning.md
├── Troubleshooting.md
├── ESP32-Integration.md
├── Arduino-Integration.md
├── IoT-Temperature-Monitoring.md
├── FAQ.md
└── Common-Errors.md
```

## Why Use GitHub Wiki?

**SEO Benefits:**
- ✅ Indexed separately by Google (more pages = more visibility)
- ✅ Different URL structure (`/wiki/Page-Name`)
- ✅ Clean URLs without `.md` extension
- ✅ GitHub search ranking

**User Benefits:**
- ✅ Built-in search
- ✅ Version history
- ✅ Easy navigation with sidebar
- ✅ Collaborative editing
- ✅ Mobile-friendly

## Linking Between Pages

**Wiki internal links:**
```markdown
[Installation Guide](Installation)
[Quick Start](Quick-Start-Guide)
```

**Link to main repo:**
```markdown
[View on GitHub](https://github.com/enzolarosa/mqtt-broadcast)
[Examples](https://github.com/enzolarosa/mqtt-broadcast/tree/main/examples)
```

## Maintaining the Wiki

Keep wiki content in sync with main repo:

1. Update `.github/wiki/*.md` files in main repo
2. Copy to wiki repo when making changes
3. Or use GitHub Actions to auto-sync (advanced)

## Example GitHub Actions Auto-Sync

```yaml
# .github/workflows/sync-wiki.yml
name: Sync Wiki

on:
  push:
    paths:
      - '.github/wiki/**'
    branches:
      - main

jobs:
  sync:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Clone wiki
        run: |
          git clone https://github.com/${{ github.repository }}.wiki.git wiki

      - name: Copy files
        run: |
          cp .github/wiki/*.md wiki/

      - name: Push to wiki
        run: |
          cd wiki
          git config user.name "GitHub Actions"
          git config user.email "actions@github.com"
          git add .
          git commit -m "Sync from main repo" || exit 0
          git push
```

## Next Steps

1. Create wiki pages manually or via git clone
2. Add content based on README sections
3. Link wiki from README: `[📖 Wiki Documentation](https://github.com/enzolarosa/mqtt-broadcast/wiki)`
4. Submit to Awesome Laravel lists
5. Monitor wiki traffic in GitHub Insights
