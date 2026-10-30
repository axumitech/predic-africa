import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Panel } from './ui';

export default function NotificationPreferences({ enabled }: { enabled: boolean }) {
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    return <Panel title="Préférences de notification"><div className="p-notification-preference"><div><strong>Recevoir les notifications</strong><p>Activez ou désactivez les nouvelles notifications dans l’application. Les notifications déjà reçues restent consultables.</p></div>
        <button type="button" role="switch" aria-checked={enabled} aria-label="Recevoir les notifications" disabled={saving} className="p-notification-toggle" onClick={() => {
            setSaving(true); setError('');
            router.post('/notifications/preferences', { notifications_enabled: !enabled }, { preserveScroll: true,
                onError: () => setError('La préférence n’a pas pu être enregistrée. Réessayez.'), onFinish: () => setSaving(false) });
        }}><span aria-hidden="true"/></button></div><p className="p-notification-preference-status" role="status">{saving ? 'Enregistrement…' : enabled ? 'Notifications activées' : 'Notifications désactivées'}</p>{error && <p className="p-error" role="alert">{error}</p>}
    </Panel>;
}
