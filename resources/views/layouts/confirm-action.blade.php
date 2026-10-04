<dialog id="confirm-action" class="modal" aria-labelledby="confirm-action-title" aria-describedby="confirm-action-message">
    <div class="modal-box max-w-md rounded-sm border border-base-300 bg-base-100 p-0">
        <div class="p-5 sm:p-6">
            <div class="flex items-center gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-sm border border-error/15 bg-error/10 text-error" aria-hidden="true"><x-lucide-triangle-alert class="size-4" /></span>
                <h2 id="confirm-action-title" class="text-base font-semibold tracking-tight" data-confirm-dialog-title>Confirm action</h2>
            </div>
            <p id="confirm-action-message" class="mt-4 text-sm leading-relaxed text-base-content/70" data-confirm-dialog-message></p>
        </div>
        <div class="modal-action m-0 border-t border-base-300 bg-base-200/50 px-5 py-3 sm:px-6">
            <button class="btn btn-outline rounded-sm" type="button" data-confirm-cancel autofocus>Cancel</button>
            <button class="btn btn-error rounded-sm" type="button" data-confirm-accept>Confirm</button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>Cancel</button></form>
</dialog>
