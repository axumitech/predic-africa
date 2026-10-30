import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import '../../css/app.css';
import '../features/platform/login.css';
import '../features/platform/register.css';

type Field = 'name' | 'email' | 'password' | 'password_confirmation';
type RegistrationField = Field | 'country' | 'currency';
type CountryOption = { code: string; name: string; currencies: { code: string; name: string }[] };
const fields: { key: Field; label: string; placeholder: string; autoComplete: string; maxLength?: number }[] = [
    { key: 'name', label: 'Nom complet', placeholder: 'Votre nom et prénom', autoComplete: 'name', maxLength: 100 },
    { key: 'email', label: 'Adresse e-mail', placeholder: 'vous@exemple.com', autoComplete: 'email', maxLength: 255 },
    { key: 'password', label: 'Mot de passe', placeholder: 'Au moins 10 caractères', autoComplete: 'new-password' },
    { key: 'password_confirmation', label: 'Confirmer le mot de passe', placeholder: 'Saisissez-le à nouveau', autoComplete: 'new-password' },
];
export default function Register({ countries }: { countries: CountryOption[] }) {
    const form = useForm({ country: '', currency: '', name: '', email: '', password: '', password_confirmation: '' });
    const inputs = useRef<Partial<Record<RegistrationField, HTMLInputElement | HTMLSelectElement | null>>>({});
    const country = countries.find(option => option.code === form.data.country);
    const [visible, setVisible] = useState(false);
    const strength = Math.min(4, Number(form.data.password.length >= 10) + Number(form.data.password.length >= 14) + Number(/[a-z]/.test(form.data.password) && /[A-Z]/.test(form.data.password)) + Number(/[0-9]/.test(form.data.password) && /[^a-zA-Z0-9]/.test(form.data.password)));
    const matches = form.data.password_confirmation.length > 0 && form.data.password === form.data.password_confirmation;
    return <main className="pa-login pa-register">
        <Head title="Créer un compte — PredicAfrica" />
        <section className="pa-login-story" aria-label="PredicAfrica">
            <Link href="/" className="pa-login-brand" aria-label="PredicAfrica — Accueil"><img width="1460" height="481" src="/HOR_WG.png" alt="PredicAfrica" /></Link>
            <div className="pa-login-story-body">
                <span className="pa-login-eyebrow"><span /> VOS CONVICTIONS ONT UNE PLACE</span>
                <h2>Votre regard.<br />Vos idées.<br /><em>Votre prochain coup d’avance.</em></h2>
                <p>Découvrez une autre façon de suivre l’actualité africaine. Faites vos premiers pronostics et voyez où vos intuitions vous mènent.</p>
                <ol className="pa-register-steps">
                    <li><span>01</span><div><strong>Découvrez les marchés</strong><p>Sport, culture, politique, économie…</p></div></li>
                    <li><span>02</span><div><strong>Exprimez votre conviction</strong><p>Choisissez Oui ou Non à cote fixe.</p></div></li>
                    <li><span>03</span><div><strong>Suivez vos positions</strong><p>Retrouvez vos résultats dans votre espace.</p></div></li>
                </ol>
            </div>
            <p className="pa-login-story-note">Votre pays. Votre monnaie. Votre portefeuille personnel.</p>
        </section>
        <section className="pa-login-access" aria-labelledby="pa-register-title">
            <header className="pa-login-top"><Link href="/">← Retour à l’accueil</Link><span>BIENVENUE SUR PREDICAFRICA</span></header>
            <div className="pa-login-form-area">
                <div className="pa-login-welcome"><span className="pa-login-overline">VOTRE AVENTURE COMMENCE ICI</span><h1 id="pa-register-title">Faites place à<br />vos convictions.</h1><p>Créez votre compte et explorez les marchés.<br />Aucun moyen de paiement nécessaire.</p></div>
                <form className="pa-login-form" onSubmit={event => {
                    event.preventDefault();
                    if (form.processing) return;
                    form.clearErrors();
                    if (form.data.password !== form.data.password_confirmation) {
                        form.setError('password_confirmation', 'Les mots de passe ne correspondent pas.');
                        inputs.current.password_confirmation?.focus();
                        return;
                    }
                    form.post('/register', {
                        onError: errors => { const field = (['country', 'currency', ...fields.map(({ key }) => key)] as RegistrationField[]).find(key => errors[key]); if (field) inputs.current[field]?.focus(); },
                        onSuccess: () => form.reset('password', 'password_confirmation'),
                    });
                }}>
                    <fieldset className="pa-register-region">
                        <legend>Votre pays et votre portefeuille</legend>
                        <p>Choisissez votre pays, puis confirmez la monnaie de votre portefeuille.</p>
                        <div className="pa-register-region-fields">
                            <div className="pa-login-field">
                                <label htmlFor="pa-register-country">Pays de résidence</label>
                                <select ref={element => { inputs.current.country = element; }} id="pa-register-country" name="country" autoComplete="country" required value={form.data.country} onChange={event => {
                                    form.setData(data => ({ ...data, country: event.target.value, currency: '' }));
                                    form.clearErrors('country', 'currency');
                                }} aria-invalid={!!form.errors.country} aria-describedby={form.errors.country ? 'pa-register-country-error' : undefined}>
                                    <option value="" disabled>Choisir mon pays</option>
                                    {countries.map(option => <option key={option.code} value={option.code}>{option.name}</option>)}
                                </select>
                                {form.errors.country && <p id="pa-register-country-error" className="pa-login-error" role="alert">{form.errors.country}</p>}
                            </div>
                            <div className="pa-login-field">
                                <label htmlFor="pa-register-currency">Devise du portefeuille</label>
                                <select ref={element => { inputs.current.currency = element; }} id="pa-register-currency" name="currency" required disabled={!country} value={form.data.currency} onChange={event => {
                                    form.setData('currency', event.target.value);
                                    form.clearErrors('currency');
                                }} aria-invalid={!!form.errors.currency} aria-describedby={form.errors.currency ? 'pa-register-currency-error' : 'pa-register-region-summary'}>
                                    <option value="" disabled>{country ? 'Choisir ma devise' : 'Choisir d’abord un pays'}</option>
                                    {country?.currencies.map(option => <option key={option.code} value={option.code}>{option.code} — {option.name}</option>)}
                                </select>
                                {form.errors.currency && <p id="pa-register-currency-error" className="pa-login-error" role="alert">{form.errors.currency}</p>}
                            </div>
                        </div>
                        <div id="pa-register-region-summary" className="pa-register-region-summary" role="status">
                            {form.data.currency ? <><strong>Portefeuille personnel en {form.data.currency}</strong><span>{country?.name} · Votre devise sera conservée après l’inscription.</span><span>Les moyens de recharge disponibles dépendent de votre pays. Les participations utilisent un taux de change confirmé.</span></> : <span>La liste des monnaies s’adapte au pays choisi.</span>}
                        </div>
                    </fieldset>
                    {fields.map(({ key, label, placeholder, autoComplete, maxLength }) => {
                        const secret = key === 'password' || key === 'password_confirmation';
                        const hint = key === 'password' ? 'pa-register-password-hint' : undefined;
                        return <div className="pa-login-field" key={key}>
                            <div className="pa-register-label"><label htmlFor={`pa-register-${key}`}>{label}</label>{key === 'password' && <button type="button" aria-pressed={visible} onClick={() => setVisible(!visible)}>{visible ? 'Masquer' : 'Afficher'}<span className="pa-register-sr"> les mots de passe</span></button>}</div>
                            <input ref={element => { inputs.current[key] = element; }} id={`pa-register-${key}`} name={key} type={secret ? (visible ? 'text' : 'password') : key === 'email' ? 'email' : 'text'} autoComplete={autoComplete} autoCapitalize={key === 'name' ? 'words' : 'none'} spellCheck={false} required minLength={secret ? 10 : undefined} maxLength={maxLength} placeholder={placeholder} value={form.data[key]} onChange={e => { form.setData(key, e.target.value); form.clearErrors(key); }} aria-invalid={!!form.errors[key]} aria-describedby={[hint, form.errors[key] ? `pa-register-${key}-error` : undefined].filter(Boolean).join(' ') || undefined}/>
                            {key === 'password' && <small id={hint} className="pa-register-hint">Utilisez au moins 10 caractères.</small>}{key === 'password' && form.data.password && <div className="pa-password-strength" role="status"><meter min={0} max={4} value={strength} aria-label="Robustesse indicative du mot de passe"/><small>{['Trop court', 'À renforcer', 'Correct', 'Bon', 'Robuste'][strength]} · Indication locale, non transmise</small></div>}
                            {key === 'password_confirmation' && matches && <small className="pa-register-match" role="status">✓ Les mots de passe correspondent.</small>}
                            {form.errors[key] && <p id={`pa-register-${key}-error`} className="pa-login-error" role="alert">{form.errors[key]}</p>}
                        </div>;
                    })}
                    <button type="submit" className="pa-login-submit" disabled={form.processing} aria-busy={form.processing}>{form.processing ? 'Création de votre compte…' : 'Créer mon compte'}<span aria-hidden="true">→</span></button>
                </form>
                <p className="pa-login-register">Déjà un compte ? <Link href="/login">Connectez-vous ↗</Link></p>
            </div>
            <footer className="pa-login-footer"><Link href="/help">Besoin d’aide ?</Link><div><Link href="/privacy">Confidentialité</Link><Link href="/terms">Conditions</Link></div></footer>
        </section>
    </main>;
}
