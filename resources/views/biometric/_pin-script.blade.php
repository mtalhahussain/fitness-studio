{{-- Machine PIN actions for member/trainer lists. Call with the Alpine row object; it updates u.biometric_code. --}}
<script>
window.machinePin = {
    async push(u) {
        try {
            const res = await post(`/biometric/users/${u.id}/push`, {});
            u.biometric_code = res.biometric_code;
            toast(res.queued
                ? `PIN ${res.biometric_code} queued to ${res.queued} machine(s)`
                : `PIN ${res.biometric_code} — no active ZKTeco machine to send to (or already queued)`, res.queued ? 'success' : 'info');
        } catch (e) { toast(e.message, 'error'); }
    },
    async regenerate(u) {
        if (!confirm(`Give ${u.name} a new machine PIN?\n\nThe old PIN and its fingerprints are deleted from the machines — ${u.name} must enroll the finger again.`)) return;
        try {
            const res = await post(`/biometric/users/${u.id}/regenerate`, {});
            u.biometric_code = res.biometric_code;
            toast(`New PIN ${res.biometric_code} — re-enroll the finger on the machine`);
        } catch (e) { toast(e.message, 'error'); }
    },
};
</script>
