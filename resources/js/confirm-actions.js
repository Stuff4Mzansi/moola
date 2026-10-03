document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('confirm-action');
    if (!dialog) return;
    const accept = dialog.querySelector('[data-confirm-accept]');
    const message = dialog.querySelector('[data-confirm-dialog-message]');
    const approvedForms = new WeakSet();
    let pendingForm = null;
    let pendingSubmitter = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
        if (approvedForms.has(form)) { approvedForms.delete(form); return; }
        event.preventDefault();
        event.stopImmediatePropagation();
        if (dialog.open) return;
        pendingForm = form;
        pendingSubmitter = event.submitter;
        dialog.querySelector('[data-confirm-dialog-title]').textContent = form.dataset.confirmTitle || 'Confirm action';
        message.textContent = form.dataset.confirm;
        accept.textContent = form.dataset.confirmLabel || 'Confirm';
        accept.disabled = false;
        form.closest('details')?.removeAttribute('open');
        dialog.showModal();
    }, true);

    accept.addEventListener('click', () => {
        if (!pendingForm?.isConnected) {
            message.textContent = 'This item changed while the dialog was open. Cancel and try again from the updated page.';
            accept.disabled = true;
            return;
        }
        const form = pendingForm;
        const submitter = pendingSubmitter?.isConnected ? pendingSubmitter : null;
        accept.disabled = true;
        approvedForms.add(form);
        dialog.close();
        form.requestSubmit(submitter);
        approvedForms.delete(form);
    });
    dialog.querySelector('[data-confirm-cancel]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { pendingForm = null; pendingSubmitter = null; });
});
