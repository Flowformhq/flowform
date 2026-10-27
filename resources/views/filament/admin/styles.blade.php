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

  /* Logo Theme Toggling & Geometry */
  img.fi-logo-dark {
    display: none !important;
  }
  img.fi-logo-light {
    display: block !important;
  }

  html.dark img.fi-logo-dark {
    display: block !important;
  }
  html.dark img.fi-logo-light {
    display: none !important;
  }

  img.fi-logo {
    height: 2.25rem !important;
    width: auto !important;
    max-width: 100% !important;
  }

  .fi-simple-header {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
  }

  .fi-simple-header img.fi-logo {
    margin: 0 auto 1.5rem auto !important;
  }
</style>
