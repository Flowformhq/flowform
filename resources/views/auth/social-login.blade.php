<div class="social-auth-container">
    <div class="social-auth-divider">
        <span>or</span>
    </div>

    <div class="social-auth-buttons">
        <a href="{{ route('social.redirect', 'google') }}" class="social-auth-btn">
            <span class="social-auth-content">
                <span class="social-auth-visual">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; min-height: 16px; max-height: 16px; display: block;" class="social-auth-icon">
                        <g clip-path="url(#google-g-clip)">
                            <path d="M8.00018 3.16667C9.18018 3.16667 10.2368 3.57333 11.0702 4.36667L13.3535 2.08333C11.9668 0.793333 10.1568 0 8.00018 0C4.87352 0 2.17018 1.79333 0.853516 4.40667L3.51352 6.47C4.14352 4.57333 5.91352 3.16667 8.00018 3.16667Z" fill="#EA4335"></path>
                            <path d="M15.66 8.18335C15.66 7.66002 15.61 7.15335 15.5333 6.66669H8V9.67335H12.3133C12.12 10.66 11.56 11.5 10.72 12.0667L13.2967 14.0667C14.8 12.6734 15.66 10.6134 15.66 8.18335Z" fill="#4285F4"></path>
                            <path d="M3.51 9.53001C3.35 9.04668 3.25667 8.53334 3.25667 8.00001C3.25667 7.46668 3.34667 6.95334 3.51 6.47001L0.85 4.40668C0.306667 5.48668 0 6.70668 0 8.00001C0 9.29334 0.306667 10.5133 0.853333 11.5933L3.51 9.53001Z" fill="#FBBC05"></path>
                            <path d="M8.0001 16C10.1601 16 11.9768 15.29 13.2968 14.0633L10.7201 12.0633C10.0034 12.5467 9.0801 12.83 8.0001 12.83C5.91343 12.83 4.14343 11.4233 3.5101 9.52667L0.850098 11.59C2.1701 14.2067 4.87343 16 8.0001 16Z" fill="#34A853"></path>
                        </g>
                        <defs>
                            <clipPath id="google-g-clip">
                                <rect width="16" height="16" fill="white"></rect>
                            </clipPath>
                        </defs>
                    </svg>
                </span>
                <span class="social-auth-label">Continue with Google</span>
            </span>
        </a>

        <a href="{{ route('social.redirect', 'github') }}" class="social-auth-btn">
            <span class="social-auth-content">
                <span class="social-auth-visual">
                    <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; min-height: 16px; max-height: 16px; display: block;" class="social-auth-icon octicon-mark-github">
                        <path d="M10.226 17.284c-2.965-.36-5.054-2.493-5.054-5.256 0-1.123.404-2.336 1.078-3.144-.292-.741-.247-2.314.09-2.965.898-.112 2.111.36 2.83 1.01.853-.269 1.752-.404 2.853-.404 1.1 0 1.999.135 2.807.382.696-.629 1.932-1.1 2.83-.988.315.606.36 2.179.067 2.942.72.854 1.101 2 1.101 3.167 0 2.763-2.089 4.852-5.098 5.234.763.494 1.28 1.572 1.28 2.807v2.336c0 .674.561 1.056 1.235.786 4.066-1.55 7.255-5.615 7.255-10.646C23.5 6.188 18.334 1 11.978 1 5.62 1 .5 6.188.5 12.545c0 4.986 3.167 9.12 7.435 10.669.606.225 1.19-.18 1.19-.786V20.63a2.9 2.9 0 0 1-1.078.224c-1.483 0-2.359-.808-2.987-2.313-.247-.607-.517-.966-1.034-1.033-.27-.023-.359-.135-.359-.27 0-.27.45-.471.898-.471.652 0 1.213.404 1.797 1.235.45.651.921.943 1.483.943.561 0 .92-.202 1.437-.719.382-.381.674-.718.944-.943"></path>
                    </svg>
                </span>
                <span class="social-auth-label">Continue with GitHub</span>
            </span>
        </a>
    </div>
</div>

<style>
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
