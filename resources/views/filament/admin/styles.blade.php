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

  /* Social Auth Buttons (GitHub Primer Style) */
  .social-auth-container {
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;
    margin-top: 1rem !important;
  }
  .social-auth-divider {
    display: flex !important;
    align-items: center !important;
    text-align: center !important;
    margin: 0.75rem 0 1rem 0 !important;
  }
  .social-auth-divider::before,
  .social-auth-divider::after {
    content: '' !important;
    flex: 1 1 0% !important;
    border-top: 1px solid #1e293b !important;
  }
  html:not(.dark) .social-auth-divider::before,
  html:not(.dark) .social-auth-divider::after {
    border-top: 1px solid #e2e8f0 !important;
  }
  .social-auth-divider span {
    padding: 0 0.75rem !important;
    font-size: 0.75rem !important;
    color: #64748b !important;
    text-transform: lowercase !important;
    letter-spacing: 0.05em !important;
    font-family: inherit !important;
  }
  html:not(.dark) .social-auth-divider span {
    color: #94a3b8 !important;
  }
  .social-auth-buttons {
    display: flex !important;
    flex-direction: column !important;
    gap: 0.5rem !important;
    width: 100% !important;
  }
  .social-auth-btn {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 100% !important;
    box-sizing: border-box !important;
    min-height: 2.375rem !important;
    padding: 0.375rem 1rem !important;
    border-radius: 0.5rem !important;
    font-size: 0.875rem !important;
    font-weight: 500 !important;
    line-height: 1.25rem !important;
    text-decoration: none !important;
    cursor: pointer !important;
    transition: all 0.15s ease-in-out !important;
    user-select: none !important;
    background-color: #1e293b !important;
    border: 1px solid #334155 !important;
    color: #f1f5f9 !important;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
  }
  .social-auth-btn:hover {
    background-color: #334155 !important;
    border-color: #475569 !important;
    color: #ffffff !important;
    text-decoration: none !important;
  }
  html:not(.dark) .social-auth-btn {
    background-color: #f8fafc !important;
    border: 1px solid #cbd5e1 !important;
    color: #1e293b !important;
  }
  html:not(.dark) .social-auth-btn:hover {
    background-color: #f1f5f9 !important;
    border-color: #94a3b8 !important;
    color: #0f172a !important;
  }
  .social-auth-content {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.5rem !important;
  }
  .social-auth-visual {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 1rem !important;
    height: 1rem !important;
    min-width: 1rem !important;
    max-width: 1rem !important;
    flex-shrink: 0 !important;
  }
  .social-auth-icon {
    width: 1rem !important;
    height: 1rem !important;
    min-width: 1rem !important;
    max-width: 1rem !important;
    min-height: 1rem !important;
    max-height: 1rem !important;
    display: block !important;
    flex-shrink: 0 !important;
  }
  .social-auth-label {
    font-size: 0.875rem !important;
    font-weight: 500 !important;
    line-height: 1.25rem !important;
    white-space: nowrap !important;
  }
</style>
