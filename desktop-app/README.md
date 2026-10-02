# Project Aruga Pretest Desktop App (v2, Phase 1)

A Windows app (.exe installer) that shows the profiling form and saves profiles
to the **pretest** database tables. It never writes to the live tables.

- The form asks exactly the same questions as the live tool, copied from `public/`.
- It logs in with the tester's existing Interviewer Code, but only codes listed in
  `pretest.pretest_users` are accepted.
- It talks only to `api/pretest-router.php`. The app contains no database keys.
- Phase 1 needs internet. Offline saving comes in Phase 2.

```
desktop-app/
  src/                 the screens (HTML/JS/CSS), shown inside the app window
    data/              dropdown and location lists, copied from api/admin-router.php
    js/config.js       server address and app version
    js/pretest-api.js  the only code that calls the server
    js/profiling.js    copy of public/js/profiling.js with pretest changes (see header)
  src-tauri/           Tauri (desktop wrapper) settings and installer config
  scripts/             helper scripts
```

This folder is listed in `.vercelignore`, so it is never uploaded to Vercel and
adds nothing to the Vercel functions.

## One-time setup (the computer that builds the installer)

1. **Microsoft C++ Build Tools**: https://visualstudio.microsoft.com/visual-cpp-build-tools/
   Choose the "Desktop development with C++" workload (about 6 GB).
2. **Rust**: https://rustup.rs (run `rustup-init.exe` and accept the defaults).
3. Open a new terminal, then from this folder run: `npm install`

WebView2 is already part of Windows 11. On Windows 10, the installer downloads it if needed.

## Build the installer

```bash
cd desktop-app
npm run build
```

The first build downloads and compiles dependencies (10–20 minutes). Later builds are faster.
The installer is created here:

```
desktop-app/src-tauri/target/release/bundle/nsis/Project Aruga Pretest_0.1.0_x64-setup.exe
```

Share it with testers through Google Drive, not through Vercel or git.

Windows will show "Unknown publisher" / SmartScreen because the installer is
not code-signed. Testers click **More info → Run anyway**.

## Try it without building an installer

```bash
cd desktop-app
npm run dev
```

## When the live form changes

The pretest copy does not update itself.

- **Dropdown or location lists changed:** run `npm run export-data`.
- **Questions or validation changed:** copy the change into `src/js/profiling.js`
  by hand, keeping the pretest changes listed at the top of that file.

## Releasing a new version

1. Raise the version in **all three** places: `src/js/config.js` (`APP_VERSION`),
   `src-tauri/tauri.conf.json` and `src-tauri/Cargo.toml`.
2. Run `npm run build` and share the new installer.

Every pretest record stores the app version it was submitted with.
