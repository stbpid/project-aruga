// The app is the pretest web form (../src) shown in a desktop window.
// It needs no native commands in Phase 1; offline storage is added in Phase 2.
#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .run(tauri::generate_context!())
        .expect("error while running Project Aruga Pretest");
}
