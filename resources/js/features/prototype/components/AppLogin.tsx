import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function AppLogin() {
 return (<div id={"app-login"} style={{"display":"none"}}>
<div className={"login-container"}>


<div className={"login-header-bar"}>
<button type={"button"} className={"btn btn--outline btn--sm"} id={"btn-login-back-home"}>
<svg viewBox={"0 0 24 24"} fill={"none"} width={"16"} height={"16"} stroke={"currentColor"} strokeWidth={"2"}>
<path d={"M19 12H5M12 19l-7-7 7-7"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>

<span>{"Retour à l'accueil"}</span>
</button>

<img src="/HOR_WG.png" alt={"Wegame Logo"} className={"brand-img"} style={{"height":"32px","width":"auto"}} />
</div>



<div className={"login-card"}>
<div className={"login-card__badge"}>
<span className={"pulse-dot"}></span>

<span>{"Accès Sécurisé Bourse & Wallet"}</span>
</div>

<h2 className={"login-card__title"}>{"Connexion à votre Compte"}</h2>

<p className={"login-card__subtitle"}>{"Identifiez-vous pour négocier sur la Bourse d'Opinions P2P et gérer votre portefeuille Mobile Money."}</p>



<div className={"login-tabs"}>
<button type={"button"} className={"login-tab active"} id={"tab-login-phone"} data-mode={"phone"}>
<svg viewBox={"0 0 24 24"} fill={"none"} width={"16"} height={"16"} stroke={"currentColor"} strokeWidth={"2"}>
<rect x={"5"} y={"2"} width={"14"} height={"20"} rx={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></rect>

<line x1={"12"} y1={"18"} x2={"12.01"} y2={"18"} strokeLinecap={"round"} strokeLinejoin={"round"}></line>
</svg>

<span>{"Mobile Money / Tél"}</span>
</button>

<button type={"button"} className={"login-tab"} id={"tab-login-email"} data-mode={"email"}>
<svg viewBox={"0 0 24 24"} fill={"none"} width={"16"} height={"16"} stroke={"currentColor"} strokeWidth={"2"}>
<path d={"M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>

<polyline points={"22,6 12,13 2,6"} strokeLinecap={"round"} strokeLinejoin={"round"}></polyline>
</svg>

<span>{"Email & Mot de passe"}</span>
</button>
</div>



<div id={"login-alert"} className={"login-alert"} style={{"display":"none"}}></div>



<form id={"form-login"} className={"login-form"}>


<div className={"form-group"} id={"group-login-phone"}>
<label htmlFor={"login-phone-number"}>{"Numéro de Téléphone (Mobile Money)"}</label>

<div className={"phone-input-wrapper"}>
<select id={"login-country-prefix"} className={"phone-prefix-select"}>
<option value={"+221"}>{"+221 (Sénégal)"}</option>

<option value={"+225"}>{"+225 (Côte d'Ivoire)"}</option>

<option value={"+237"}>{"+237 (Cameroun)"}</option>

<option value={"+223"}>{"+223 (Mali)"}</option>

<option value={"+233"}>{"+233 (Ghana)"}</option>

<option value={"+243"}>{"+243 (RDC)"}</option>

<option value={"+242"}>{"+242 (Congo)"}</option>
</select>

<input type={"tel"} id={"login-phone-number"} placeholder={"77 123 45 67"} />
</div>
</div>



<div className={"form-group"} id={"group-login-email"} style={{"display":"none"}}>
<label htmlFor={"login-email"}>{"Adresse Email"}</label>

<input type={"email"} id={"login-email"} placeholder={"ablaye.diop@predicafrika.com"} />
</div>



<div className={"form-group"}>
<div className={"label-with-action"}>
<label htmlFor={"login-password"}>{"Code PIN / Mot de passe"}</label>

<a href={"#"} id={"link-forgot-password"} className={"forgot-link"}>{"Mot de passe oublié ?"}</a>
</div>

<div className={"password-input-wrapper"}>
<input type={"password"} id={"login-password"} placeholder={"••••••••"} required />

<button type={"button"} className={"btn-toggle-pw"} id={"btn-toggle-login-pw"} title={"Afficher/Masquer"}>
<svg viewBox={"0 0 24 24"} fill={"none"} width={"18"} height={"18"} stroke={"currentColor"} strokeWidth={"2"}>
<path d={"M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"}></path>

<circle cx={"12"} cy={"12"} r={"3"}></circle>
</svg>
</button>
</div>
</div>



<div className={"form-group form-checkbox-group"}>
<label className={"custom-checkbox"}>
<input type={"checkbox"} id={"login-remember"} defaultChecked />

<span className={"checkbox-box"}></span>

<span className={"checkbox-label"}>{"Rester connecté sur cet appareil"}</span>
</label>
</div>



<button type={"submit"} className={"btn btn--primary btn--block btn--lg"} id={"btn-submit-login"}>
<span>{"Accéder à la Bourse"}</span>

<svg viewBox={"0 0 24 24"} fill={"none"} width={"18"} height={"18"} stroke={"currentColor"} strokeWidth={"2"}>
<path d={"M5 12h14M12 5l7 7-7 7"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>
</button>
</form>



<div className={"login-divider"}>
<span>{"OU CONNEXION RAPIDE DÉMO"}</span>
</div>



<div className={"demo-login-actions"}>
<button type={"button"} className={"btn btn--outline btn--block"} id={"btn-demo-trader"}>
<svg viewBox={"0 0 24 24"} fill={"none"} width={"16"} height={"16"} stroke={"currentColor"} strokeWidth={"2"} style={{"marginRight":"6px"}}>
<path d={"M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"}></path>

<circle cx={"12"} cy={"7"} r={"4"}></circle>
</svg>

<span>{"Connexion Trader (Ablaye Diop)"}</span>
</button>

<button type={"button"} className={"btn btn--outline btn--block"} id={"btn-demo-admin"}>
<svg viewBox={"0 0 24 24"} fill={"none"} width={"16"} height={"16"} stroke={"currentColor"} strokeWidth={"2"} style={{"marginRight":"6px"}}>
<path d={"M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"}></path>
</svg>

<span>{"Connexion SuperAdmin"}</span>
</button>
</div>



<div className={"login-footer-text"}>
<span>{"Pas encore inscrit ?"}</span>

<a href={"#"} id={"link-create-account"}>{"Créer un compte PredicAfrica"}</a>
</div>
</div>
</div>
</div>);
});
