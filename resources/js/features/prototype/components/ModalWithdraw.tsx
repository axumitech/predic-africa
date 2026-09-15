import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ModalWithdraw() {
 return (<div className={"modal-backdrop"} id={"modal-withdraw"}>
<div className={"modal-card"} id={"withdraw-modal-card"}>
<button className={"modal-close-btn"} id={"withdraw-close"}>{"×"}</button>



<div className={"withdraw-step active"} id={"withdraw-step-1"}>
<h3 className={"modal-title"}>{"Retirer des fonds"}</h3>

<p className={"modal-subtitle"}>{"Transférez les liquidités de votre portefeuille vers votre compte Mobile Money."}</p>

<form id={"withdraw-submit-form"} className={"modal-form"}>
<div className={"form-group"}>
<label htmlFor={"withdraw-operator"}>{"Opérateur Mobile Money local"}</label>

<select id={"withdraw-operator"} required style={{"backgroundColor":"var(--bg-tertiary)","border":"1px solid var(--border-color)","padding":"var(--space-sm) var(--space-md)","borderRadius":"var(--radius-md)","width":"100%"}}>

</select>
</div>

<div className={"form-group"}>
<label htmlFor={"withdraw-phone"}>{"Numéro de téléphone récepteur"}</label>

<div className={"input-with-prefix"}>
<span className={"phone-prefix"} id={"withdraw-phone-prefix-label"}>{"+221"}</span>

<input type={"tel"} id={"withdraw-phone"} placeholder={"77 123 45 67"} required />
</div>
</div>

<div className={"form-group"}>
<label htmlFor={"withdraw-amount"} id={"withdraw-amount-label"}>{"Montant du retrait (FCFA)"}</label>

<input type={"number"} id={"withdraw-amount"} placeholder={"Entrez le montant à retirer"} min={"500"} max={"500000"} step={"500"} required />

<div className={"wallet-balance-row"}>
<span>{"Solde disponible : "}
<strong id={"withdraw-modal-user-balance"}>{"15 000 FCFA"}</strong></span>

<button type={"button"} className={"btn-text-action"} id={"withdraw-btn-max"}>{"Tout retirer (Max)"}</button>
</div>
</div>

<div className={"error-msg-box"} id={"withdraw-error-msg"} style={{"display":"none"}}>{"\n                        Solde insuffisant pour effectuer ce retrait.\n                    "}</div>

<div className={"modal-actions-layout"}>
<button type={"button"} className={"btn btn--outline"} id={"btn-withdraw-cancel"}>{"Annuler"}</button>

<button type={"submit"} className={"btn btn--primary"} id={"btn-withdraw-submit"}>{"Valider le retrait"}</button>
</div>
</form>
</div>



<div className={"withdraw-step"} id={"withdraw-step-2"}>
<div className={"ussd-screen"}>
<div className={"ussd-header"}>
<span className={"ussd-operator-badge"} id={"withdraw-ussd-op-badge"}>{"Orange Money"}</span>

<span className={"ussd-sec-title"}>{"Notification Push"}</span>
</div>

<div className={"ussd-body"}>
<p className={"ussd-instruction"} id={"withdraw-ussd-instruction-text"}>{"\n                            Une notification de retrait mobile money a été poussée vers votre numéro. Saisissez votre code PIN secret pour confirmer la réception des fonds.\n                        "}</p>

<div className={"ussd-amount-summary"}>
<span>{"Montant à recevoir :"}</span>

<strong id={"withdraw-ussd-charge-amount"}>{"5 000 FCFA"}</strong>
</div>

<div className={"pin-input-group"}>
<input type={"password"} id={"withdraw-ussd-pin-code"} maxLength={4} placeholder={"••••"} autoComplete={"off"} required />
</div>

<div className={"simulation-note"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"} className={"icon-warning"}>
<path d={"M12 9V11M12 15H12.01M5.07183 19H18.9282C20.4678 19 21.4301 17.3333 20.6603 16L13.7321 4C12.9623 2.66667 11.0377 2.66667 10.2679 4L3.33975 16C2.56995 17.3333 3.53223 19 5.07183 19Z"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>

<span>{"Il s'agit d'une simulation sécurisée. Saisissez n'importe quel code PIN à 4 chiffres (ex: 1234) pour valider fictivement le virement."}</span>
</div>
</div>

<div className={"ussd-footer"}>
<button className={"btn btn--ussd-cancel"} id={"btn-withdraw-ussd-cancel"}>{"Refuser"}</button>

<button className={"btn btn--ussd-confirm"} id={"btn-withdraw-ussd-confirm"}>{"Accepter"}</button>
</div>
</div>



<div className={"loader-overlay"} id={"withdraw-ussd-loader"}>
<div className={"spinner"}></div>

<p className={"loader-text"} id={"withdraw-ussd-loader-text"}>{"Virement en cours..."}</p>
</div>
</div>



<div className={"withdraw-step"} id={"withdraw-step-3"}>
<div className={"success-screen"}>
<div className={"success-icon-wrap"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"} className={"success-checkmark"}>
<path d={"M20 6L9 17L4 12"} stroke={"currentColor"} strokeWidth={"3"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>

<div className={"transaction-detail-card"}>
<div className={"detail-row"}>
<span>{"ID de retrait :"}</span>

<strong id={"success-withdraw-tx-id"}>{"PA-WD-8394204"}</strong>
</div>

<div className={"detail-row"}>
<span>{"Nouveau solde :"}</span>

<strong id={"success-withdraw-new-balance"}>{"20 000 FCFA"}</strong>
</div>
</div>

<button className={"btn btn--primary btn--block"} id={"btn-withdraw-done"}>{"Fermer"}</button>
</div>
</div>
</div>
</div>




















<script src="/app.js"></script>
</div>);
});
