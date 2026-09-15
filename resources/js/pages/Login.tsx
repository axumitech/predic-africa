import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import '../../css/app.css';
import '../features/platform/login.css';

function Arrow({ back = false }: { back?: boolean }) {
    return <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d={back ? 'M19 12H5m7-7-7 7 7 7' : 'M5 12h14m-7-7 7 7-7 7'} /></svg>;
}

export default function Login() {
    const form = useForm({ email: '', password: '' });
    const [showPassword, setShowPassword] = useState(false);
    const email = useRef<HTMLInputElement>(null);
    const password = useRef<HTMLInputElement>(null);

    return <main className="pa-login">
        <Head title="Connexion — PredicAfrica" />
        <section className="pa-login-story" aria-label="PredicAfrica">
            <Link href="/" className="pa-login-brand" aria-label="PredicAfrica — Accueil"><img src="/HOR_WG.png" alt="PredicAfrica" /></Link>
            <div className="pa-login-story-body">
                <span className="pa-login-eyebrow"><span /> LA BOURSE DES CONVICTIONS</span>
                <h2>L’Afrique bouge.<br />Prenez <em>position.</em></h2>
                <p>Sport, culture, économie… retrouvez les marchés qui vous parlent et donnez une place à vos convictions.</p>
                <div className="pa-login-topics"><span>Sport</span><span>Politique</span><span>Économie</span><span>Culture</span></div>
                <div className="pa-login-market" aria-hidden="true">
                    <div className="pa-login-market-heading"><span>VOTRE PROCHAINE CONVICTION</span><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5"><path d="m4 17 6-6 4 3 6-9m-6 0h6v6" /></svg></div>
                    <p>Une question.<br /><strong>À vous de choisir.</strong></p>
                    <svg className="pa-login-chart" viewBox="0 0 400 92" fill="none" preserveAspectRatio="none"><path d="M0 76H400M0 42H400M0 8H400" stroke="currentColor" strokeOpacity=".12" strokeDasharray="3 6"/><path d="M0 75 35 70 64 74 100 55 132 61 165 36 193 44 225 29 255 38 285 18 315 25 350 8 380 16 400 4" stroke="#f0b40b" strokeWidth="2.5" strokeLinejoin="round"/><circle cx="350" cy="8" r="4" fill="#f0b40b"/></svg>
                    <div className="pa-login-market-footer"><span>OUI <b>↗</b></span><span>NON <b>↘</b></span><small>Illustration · Démonstration</small></div>
                </div>
            </div>
            <p className="pa-login-story-note"><svg width="16" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6z"/><path d="m8 12 3 3 5-6"/></svg>Des crédits fictifs pour tester vos intuitions.</p>
        </section>

        <section className="pa-login-access" aria-labelledby="pa-login-title">
            <header className="pa-login-top"><Link href="/"><Arrow back /> Retour à l’accueil</Link><span>ESPACE MEMBRE</span></header>
            <div className="pa-login-form-area">
                <div className="pa-login-welcome"><span className="pa-login-overline">VOTRE COMPTE PREDICAFRICA</span><h1 id="pa-login-title">Heureux de<br className="pa-login-title-break" /> vous retrouver.</h1><p>Connectez-vous pour retrouver vos positions<br className="pa-login-title-break" /> et suivre les marchés.</p></div>
                <form className="pa-login-form" onSubmit={event => {
                    event.preventDefault();
                    if (form.processing) return;
                    form.post('/login', {
                        onError: errors => { (errors.email ? email : password).current?.focus(); },
                        onSuccess: () => form.reset('password'),
                    });
                }}>
                    <div className="pa-login-field">
                        <label htmlFor="pa-login-email">Adresse e-mail</label>
                        <input ref={email} id="pa-login-email" name="email" type="email" autoComplete="username" autoCapitalize="none" spellCheck={false} placeholder="vous@exemple.com" required maxLength={255} value={form.data.email} onChange={e => form.setData('email', e.target.value)} aria-invalid={!!form.errors.email} aria-describedby={form.errors.email ? 'pa-login-email-error' : undefined}/>
                        {form.errors.email && <p className="pa-login-error" id="pa-login-email-error" role="alert">{form.errors.email}</p>}
                    </div>
                    <div className="pa-login-field">
                        <label htmlFor="pa-login-password">Mot de passe</label>
                        <div className="pa-login-password"><input ref={password} id="pa-login-password" name="password" type={showPassword ? 'text' : 'password'} autoComplete="current-password" placeholder="Votre mot de passe" required value={form.data.password} onChange={e => form.setData('password', e.target.value)} aria-invalid={!!form.errors.password} aria-describedby={form.errors.password ? 'pa-login-password-error' : undefined}/><button type="button" aria-label={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'} aria-pressed={showPassword} onClick={() => setShowPassword(!showPassword)}><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>{showPassword && <path d="m3 3 18 18"/>}</svg></button></div>
                        {form.errors.password && <p className="pa-login-error" id="pa-login-password-error" role="alert">{form.errors.password}</p>}
                    </div>
                    <button type="submit" className="pa-login-submit" disabled={form.processing} aria-busy={form.processing}>{form.processing ? 'Connexion en cours…' : 'Se connecter'}<Arrow /></button>
                </form>
                <p className="pa-login-register"><Link href="/register"><span>Pas encore de compte ?</span> Inscrivez-vous <span aria-hidden="true">↗</span></Link></p>
                <div className="pa-login-divider"><span>ENVIE DE DÉCOUVRIR ?</span></div>
                <Link className="pa-login-demo" href="/demo"><span className="pa-login-demo-icon" aria-hidden="true">▶</span><span><strong>Explorer la démonstration</strong><small>Découvrez la plateforme sans créer de compte.</small></span><Arrow /></Link>
            </div>
            <footer className="pa-login-footer"><Link href="/help">Besoin d’aide ?</Link><div><Link href="/privacy">Confidentialité</Link><Link href="/terms">Conditions</Link></div></footer>
        </section>
    </main>;
}
