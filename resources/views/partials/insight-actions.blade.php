{{-- Print + Excel buttons shared by the Tahap 7 report pages. --}}
<button type="button" id="insightPrintBtn"
    class="flex items-center gap-2 border border-[var(--brand-600)] text-[var(--brand-600)] hover:bg-[var(--brand-600)] hover:text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
    <i class="fa-solid fa-print"></i> {{ __('insight.common.print') }}
</button>
<button type="button" id="insightExportBtn" data-filename="{{ $filename }}"
    class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
    <i class="fa-solid fa-file-excel"></i> {{ __('insight.common.export_excel') }}
</button>
