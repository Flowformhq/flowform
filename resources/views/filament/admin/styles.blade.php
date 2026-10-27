<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700;800&family=Google+Sans+Text:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>
  /* Cybernetic Modern Theme Overrides for FlowForm Filament 5 Admin */
  :root {
    --font-family: 'Google Sans Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --font-heading: 'Google Sans', 'Plus Jakarta Sans', system-ui, sans-serif;
  }
  
  body, .fi-body, .fi-simple-layout {
    background-color: #0b0f19 !important;
    font-family: var(--font-family) !important;
  }
  
  .fi-sidebar {
    background-color: #0b0f19 !important;
    border-right: 1px solid #1e293b !important;
  }
  
  .fi-topbar {
    background-color: #0b0f19 !important;
    border-bottom: 1px solid #1e293b !important;
  }

  .fi-sidebar-item-label, .fi-header-heading, h1, h2, h3, .fi-breadcrumbs {
    font-family: var(--font-heading) !important;
    letter-spacing: -0.01em;
  }

  .fi-ta-header-cell {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
    font-size: 0.75rem !important;
    letter-spacing: 0.05em !important;
    text-transform: uppercase !important;
    color: #94a3b8 !important;
  }

  .fi-card, .fi-section, .fi-ta-ctn, .fi-simple-main {
    background-color: #0f172a !important;
    border: 1px solid #1e293b !important;
    border-radius: 0.75rem !important;
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.4) !important;
  }

  /* FlowForm Brand Lockup (Icon Mark + Live Typography) */
  .flowform-brand-lockup {
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.625rem !important;
    text-decoration: none !important;
  }

  .fi-simple-header .flowform-brand-lockup,
  .fi-simple-header .fi-logo {
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    margin: 0 auto 1.5rem auto !important;
  }

  html.dark .flowform-mark-dark {
    display: block !important;
  }
  html.dark .flowform-mark-light {
    display: none !important;
  }
  html:not(.dark) .flowform-mark-dark {
    display: none !important;
  }
  html:not(.dark) .flowform-mark-light {
    display: block !important;
  }

  .flowform-word-flow {
    color: #f8fafc !important;
  }
  html:not(.dark) .flowform-word-flow {
    color: #0f172a !important;
  }

  .flowform-word-form {
    color: #00f0ff !important;
  }
  html:not(.dark) .flowform-word-form {
    color: #0284c7 !important;
  }
</style>
