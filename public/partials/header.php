<?php
/**
 * Shared site header (nav-pill + mobile menu).
 *
 * Expects (optional):
 *   $activeNav        - 'profiling' | 'dashboard'  (which switch chip is highlighted). Default 'profiling'.
 *   $interactiveSwitch - bool. true on index.html, where JS toggles between in-page
 *                         sections without navigating. false elsewhere, where the
 *                         switch is just two links to '/' and '/#dashboard'.
 *   $activePage        - 'privacy' | 'user-manual' | 'contact' | null (default). Highlights
 *                         the matching nav link in blue for the current page.
 */
$activeNav = $activeNav ?? 'profiling';
$interactiveSwitch = $interactiveSwitch ?? false;
$isDashboard = $activeNav === 'dashboard';
$activePage = $activePage ?? null;

$navLinkClass = function (string $page) use ($activePage) {
    return $activePage === $page
        ? 'text-[11.5px] font-semibold text-brand-blue transition-colors'
        : 'text-[11.5px] font-semibold text-gray-600 hover:text-brand-blue transition-colors';
};
$mobileNavLinkClass = function (string $page) use ($activePage) {
    return $activePage === $page
        ? 'block py-2.5 px-3 text-sm font-semibold text-brand-blue bg-gray-50 rounded-lg transition-colors'
        : 'block py-2.5 px-3 text-sm font-semibold text-gray-700 hover:text-brand-blue hover:bg-gray-50 rounded-lg transition-colors';
};
?>
  <header id="site-header" class="relative w-full sticky top-0 z-50 px-4 pt-4 transition-all duration-300">
    <div id="nav-pill" class="max-w-4xl mx-auto rounded-xl px-4 sm:px-5 h-14 flex items-center justify-between border border-gray-200/80 shadow-[0_2px_8px_rgba(0,0,0,0.04)] transition-all duration-300">

      <div class="flex items-center gap-3">
        <img id="img-nav-logo" src="/images/logo.webp" alt="Project Aruga Logo" class="h-9 w-auto object-contain rounded-full">
        <h2 class="font-bold text-brand-dark text-sm sm:text-base">Project Aruga</h2>
      </div>

      <nav class="hidden md:flex items-center gap-6">
        <a href="/privacy" class="<?= $navLinkClass('privacy') ?>">Privacy Policy</a>
        <a href="/user-manual" class="<?= $navLinkClass('user-manual') ?>">Documentation</a>
        <a href="/contact" class="<?= $navLinkClass('contact') ?>">Contact us</a>

        <?php if ($interactiveSwitch): ?>
        <button id="nav-toggle-switch" type="button" role="switch" aria-checked="false" aria-label="Switch between Profiling Tool and Dashboard" class="relative flex items-center h-9 p-1 rounded-xl bg-gray-200 transition-colors duration-300 shadow-inner">
          <span id="nav-toggle-thumb" class="absolute top-1 bottom-1 left-1 rounded-lg bg-brand-blue shadow-sm transition-all duration-300 ease-in-out"></span>
          <span id="nav-toggle-label-profiling" class="relative z-10 px-4 h-full flex items-center text-[11.5px] font-semibold text-white transition-colors duration-300">Profiling Tool</span>
          <span id="nav-toggle-label-dashboard" class="relative z-10 px-4 h-full flex items-center text-[11.5px] font-semibold text-gray-500 transition-colors duration-300">Dashboard</span>
        </button>
        <?php else: ?>
        <div class="flex items-center h-9 p-1 rounded-xl bg-gray-200 shadow-inner">
          <a href="/" class="px-4 h-full flex items-center text-[11.5px] font-semibold rounded-lg transition-colors <?= $isDashboard ? 'text-gray-500 hover:text-gray-700' : 'text-white bg-brand-blue shadow-sm' ?>">Profiling Tool</a>
          <a href="/#dashboard" class="px-4 h-full flex items-center text-[11.5px] font-semibold rounded-lg transition-colors <?= $isDashboard ? 'text-white bg-brand-blue shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">Dashboard</a>
        </div>
        <?php endif; ?>
      </nav>

      <button id="mobile-menu-btn" class="md:hidden text-gray-600 focus:outline-none p-1" onclick="toggleMobileMenu()">
        <svg id="menu-icon-open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
        <svg id="menu-icon-close" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
      </button>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden md:hidden max-w-4xl mx-auto mt-2 bg-white border border-gray-200/80 rounded-2xl shadow-sm px-4 py-3 space-y-1">
      <a href="/privacy" class="<?= $mobileNavLinkClass('privacy') ?>">Privacy Policy</a>
      <a href="/user-manual" class="<?= $mobileNavLinkClass('user-manual') ?>">Documentation</a>
      <a href="/contact" class="<?= $mobileNavLinkClass('contact') ?>">Contact us</a>

      <?php if ($interactiveSwitch): ?>
      <button id="nav-toggle-switch-mobile" type="button" role="switch" aria-checked="false" aria-label="Switch between Profiling Tool and Dashboard" class="relative w-full flex items-center h-10 p-1 rounded-xl bg-gray-200 transition-colors duration-300 shadow-inner mt-1">
        <span id="nav-toggle-thumb-mobile" class="absolute top-1 bottom-1 left-1 rounded-lg bg-brand-blue shadow-sm transition-all duration-300 ease-in-out"></span>
        <span id="nav-toggle-label-profiling-mobile" class="relative z-10 flex-1 h-full flex items-center justify-center text-xs font-semibold text-white transition-colors duration-300">Profiling Tool</span>
        <span id="nav-toggle-label-dashboard-mobile" class="relative z-10 flex-1 h-full flex items-center justify-center text-xs font-semibold text-gray-500 transition-colors duration-300">Dashboard</span>
      </button>
      <?php else: ?>
      <div class="flex items-center p-1 rounded-xl bg-gray-200 shadow-inner mt-1">
        <a href="/" class="flex-1 py-2 flex items-center justify-center text-xs font-semibold rounded-lg transition-colors <?= $isDashboard ? 'text-gray-500' : 'text-white bg-brand-blue shadow-sm' ?>">Profiling Tool</a>
        <a href="/#dashboard" class="flex-1 py-2 flex items-center justify-center text-xs font-semibold rounded-lg transition-colors <?= $isDashboard ? 'text-white bg-brand-blue shadow-sm' : 'text-gray-500' ?>">Dashboard</a>
      </div>
      <?php endif; ?>
    </div>
  </header>

  <script>
    function toggleMobileMenu() {
      const menu = document.getElementById('mobile-menu');
      const iconOpen = document.getElementById('menu-icon-open');
      const iconClose = document.getElementById('menu-icon-close');
      const isHidden = menu.classList.contains('hidden');
      menu.classList.toggle('hidden', !isHidden);
      iconOpen.classList.toggle('hidden', isHidden);
      iconClose.classList.toggle('hidden', !isHidden);
    }

    function updateHeaderPillOnScroll() {
      const header = document.getElementById('site-header');
      const pill = document.getElementById('nav-pill');
      if (!header || !pill || !header.classList.contains('sticky')) return;
      const scrolled = window.scrollY > 8;
      pill.classList.toggle('bg-white', scrolled);
      pill.classList.toggle('backdrop-blur-md', scrolled);
      pill.classList.toggle('shadow-sm', scrolled);
    }
    window.addEventListener('scroll', updateHeaderPillOnScroll);
    updateHeaderPillOnScroll();
  </script>
