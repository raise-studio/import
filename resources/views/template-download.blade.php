<div style="margin-bottom: 0.5rem;">
    <a href="{{ route('raise-import.template', str_replace('\\', '_', $modelClass)) }}"
       style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.875rem; color: #3b82f6; text-decoration: none; cursor: pointer; transition: color 0.15s;"
       onmouseover="this.style.color='#2563eb'; this.style.textDecoration='underline';"
       onmouseout="this.style.color='#3b82f6'; this.style.textDecoration='none';">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 1rem; height: 1rem; flex-shrink: 0;">
            <path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z" />
            <path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" />
        </svg>
        <span style="border-bottom: 1px solid transparent;">{{ __('raise-import::messages.upload.download_template') }}</span>
    </a>
</div>
