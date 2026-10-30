import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ModalPlaceBet() {
 return (<div className={"modal-backdrop"} id={"modal-place-bet"}>
<div className={"modal-card"}>
<button className={"modal-close-btn"} id={"bet-close"}>{"×"}</button>

<h3 className={"modal-title"}>{"Prendre Position"}</h3>

<p className={"modal-subtitle"}>{"Déterminez votre investissement et achetez des parts."}</p>

<div className={"bet-details-card"}>
<div className={"bet-category-badge"} id={"bet-modal-category"}>{"Politique"}</div>

<h4 className={"bet-details-question"} id={"bet-modal-question"}>{"Présidentielle au Sénégal : Qui succédera à Bassirou Diomaye Faye ?"}</h4>

<div className={"bet-choice-odds"}>
<div className={"choice-col"}>
<span className={"col-lbl"}>{"Votre choix :"}</span>

<strong className={"col-val text--primary"} id={"bet-modal-choice"}>{"Candidat Coalition Clé"}</strong>
</div>

<div className={"odds-col"}>
<span className={"col-lbl"}>{"Cote active :"}</span>

<strong className={"col-val text--gold"} id={"bet-modal-odds"}>{"1.85"}</strong>
</div>
</div>
</div>

<form id={"place-bet-submit-form"} className={"modal-form"}>


<input type={"hidden"} id={"bet-prediction-id"} defaultValue={""} />

<input type={"hidden"} id={"bet-choice-index"} defaultValue={""} />

<div className={"form-group"}>
<label htmlFor={"bet-amount"} id={"bet-amount-label"}>{"Montant à investir (FCFA)"}</label>

<input type={"number"} id={"bet-amount"} placeholder={"Entrez le montant à investir"} min={"200"} max={"500000"} step={"100"} required />

<div className={"wallet-balance-row"}>
<span>{"Solde disponible : "}
<strong id={"bet-modal-user-balance"}>{"15 000 FCFA"}</strong></span>

<button type={"button"} className={"btn-text-action"} id={"bet-btn-max"}>{"Investir Max"}</button>
</div>
</div>

<div className={"bet-payout-estimate"}>
<div className={"estimate-row"}>
<span>{"Rendement potentiel :"}</span>

<strong id={"bet-payout-val"}>{"0 FCFA"}</strong>
</div>

<div className={"estimate-row font-sm text--muted"}>
<span>{"Multiplicateur garanti lors de l'achat :"}</span>

<span id={"bet-payout-odds-desc"}>{"@ 1.85"}</span>
</div>
</div>

<div className={"error-msg-box"} id={"bet-error-msg"} style={{"display":"none"}}>{"\n                    Solde insuffisant pour acquérir cette position.\n                "}</div>

<div className={"modal-actions-layout"}>
<button type={"button"} className={"btn btn--outline"} id={"btn-bet-cancel"}>{"Annuler"}</button>

<button type={"submit"} className={"btn btn--primary"} id={"btn-bet-confirm"}>{"Valider la position"}</button>
</div>
</form>
</div>
</div>);
});
