import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ModalRecharge() {
 return (<div className={"modal-backdrop"} id={"modal-recharge"}>
<div className={"modal-card"} id={"recharge-modal-card"}>
<button className={"modal-close-btn"} id={"recharge-close"}>{"×"}</button>



<div className={"recharge-step active"} id={"recharge-step-1"}>
<h3 className={"modal-title"}>{"Recharger via Mobile Money"}</h3>

<p className={"modal-subtitle"}>{"Sélectionnez votre opérateur de paiement local pour créditer votre compte instantanément."}</p>

<div className={"operator-grid"}>
<button className={"operator-card-btn operator--orange"} data-operator={"orange"}>
<div className={"operator-logo"}>{"OM"}</div>

<span className={"operator-name"}>{"Orange Money"}</span>
</button>

<button className={"operator-card-btn operator--mtn"} data-operator={"mtn"}>
<div className={"operator-logo"}>{"MTN"}</div>

<span className={"operator-name"}>{"MTN MoMo"}</span>
</button>

<button className={"operator-card-btn operator--airtel"} data-operator={"airtel"}>
<div className={"operator-logo"}>{"airtel"}</div>

<span className={"operator-name"}>{"Airtel Money"}</span>
</button>

<button className={"operator-card-btn operator--wave"} data-operator={"wave"}>
<div className={"operator-logo"}>{"wave"}</div>

<span className={"operator-name"}>{"Wave"}</span>
</button>

<button className={"operator-card-btn operator--moov"} data-operator={"moov"}>
<div className={"operator-logo"}>{"moov"}</div>

<span className={"operator-name"}>{"Moov Africa"}</span>
</button>
</div>
</div>



<div className={"recharge-step"} id={"recharge-step-2"}>
<h3 className={"modal-title"} id={"recharge-form-title"}>{"Paiement"}</h3>

<p className={"modal-subtitle"}>{"Indiquez votre numéro de téléphone et le montant à recharger."}</p>

<form id={"recharge-submit-form"} className={"modal-form"}>
<input type={"hidden"} id={"selected-operator-val"} defaultValue={""} />

<div className={"form-group"}>
<label htmlFor={"recharge-phone"}>{"Numéro de téléphone mobile"}</label>

<div className={"input-with-prefix"}>
<span className={"phone-prefix"} id={"phone-prefix-label"}>{"+221"}</span>

<input type={"tel"} id={"recharge-phone"} placeholder={"77 123 45 67"} required />
</div>
</div>

<div className={"form-group"}>
<label htmlFor={"recharge-amount"}>{"Montant du Dépôt (FCFA)"}</label>

<input type={"number"} id={"recharge-amount"} placeholder={"Entrez le montant"} min={"500"} max={"500000"} step={"500"} required />

<div className={"amount-presets"}>
<button type={"button"} className={"preset-btn"} data-value={"1000"}>{"1 000 F"}</button>

<button type={"button"} className={"preset-btn"} data-value={"5000"}>{"5 000 F"}</button>

<button type={"button"} className={"preset-btn"} data-value={"10000"}>{"10 000 F"}</button>

<button type={"button"} className={"preset-btn"} data-value={"25000"}>{"25 000 F"}</button>
</div>
</div>

<div className={"modal-actions-layout"}>
<button type={"button"} className={"btn btn--outline"} id={"recharge-back-to-1"}>{"Retour"}</button>

<button type={"submit"} className={"btn btn--primary"} id={"recharge-submit-btn"}>{"Initier le paiement"}</button>
</div>
</form>
</div>



<div className={"recharge-step"} id={"recharge-step-3"}>


<div className={"ussd-screen"}>
<div className={"ussd-header"}>
<span className={"ussd-operator-badge"} id={"ussd-op-badge"}>{"Orange Money"}</span>

<span className={"ussd-sec-title"}>{"Vérification de sécurité"}</span>
</div>

<div className={"ussd-body"}>


<p className={"ussd-instruction"} id={"ussd-instruction-text"}>{"\n                            Une demande de confirmation a été envoyée sur votre téléphone. Saisissez votre code PIN secret à 4 chiffres ci-dessous pour valider la transaction.\n                        "}</p>

<div className={"ussd-amount-summary"}>
<span>{"Montant à débiter :"}</span>

<strong id={"ussd-charge-amount"}>{"5 000 FCFA"}</strong>
</div>



<div className={"pin-input-group"}>
<input type={"password"} id={"ussd-pin-code"} maxLength={4} placeholder={"••••"} autoComplete={"off"} required />
</div>

<div className={"simulation-note"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"} className={"icon-warning"}>
<path d={"M12 9V11M12 15H12.01M5.07183 19H18.9282C20.4678 19 21.4301 17.3333 20.6603 16L13.7321 4C12.9623 2.66667 11.0377 2.66667 10.2679 4L3.33975 16C2.56995 17.3333 3.53223 19 5.07183 19Z"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>

<span>{"Il s'agit d'une simulation sécurisée. Saisissez n'importe quel code PIN à 4 chiffres (ex: 1234) pour valider fictivement le dépôt."}</span>
</div>
</div>

<div className={"ussd-footer"}>
<button className={"btn btn--ussd-cancel"} id={"btn-ussd-cancel"}>{"Annuler"}</button>

<button className={"btn btn--ussd-confirm"} id={"btn-ussd-confirm"}>{"Confirmer"}</button>
</div>
</div>



<div className={"loader-overlay"} id={"ussd-loader"}>
<div className={"spinner"}></div>

<p className={"loader-text"} id={"ussd-loader-text"}>{"Traitement en cours..."}</p>
</div>
</div>



<div className={"recharge-step"} id={"recharge-step-4"}>
<div className={"success-screen"}>
<div className={"success-icon-wrap"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"} className={"success-checkmark"}>
<path d={"M20 6L9 17L4 12"} stroke={"currentColor"} strokeWidth={"3"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>
</div>

<h3 className={"modal-title"}>{"Recharge réussie !"}</h3>

<p className={"success-desc"}>{"\n                        Votre portefeuille a été crédité de "}
<strong id={"success-deposited-amount"}>{"5 000 FCFA"}</strong>
{" via "}
<span id={"success-op-name"}>{"MTN MoMo"}</span>
{".\n                    "}</p>

<div className={"transaction-detail-card"}>
<div className={"detail-row"}>
<span>{"ID de transaction :"}</span>

<strong id={"success-tx-id"}>{"PA-TX-8394204"}</strong>
</div>

<div className={"detail-row"}>
<span>{"Nouveau solde :"}</span>

<strong id={"success-new-balance"}>{"20 000 FCFA"}</strong>
</div>
</div>

<button className={"btn btn--primary btn--block"} id={"btn-recharge-done"}>{"Fermer"}</button>
</div>
</div>
</div>
</div>);
});
