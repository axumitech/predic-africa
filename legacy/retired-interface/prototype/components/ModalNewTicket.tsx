import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ModalNewTicket() {
 return (<div className={"modal-backdrop"} id={"modal-new-ticket"}>
<div className={"modal-card"} style={{"maxWidth":"500px"}}>
<button className={"modal-close-btn"} id={"new-ticket-close"}>{"×"}</button>

<h3 className={"modal-title"}>{"Créer un Nouveau Ticket"}</h3>

<p className={"modal-subtitle"}>{"Soumettez votre problème ou question à notre équipe de support technique."}</p>

<form id={"form-new-ticket"} className={"modal-form"}>
<div className={"form-group"}>
<label htmlFor={"new-ticket-category"}>{"Catégorie"}</label>

<select id={"new-ticket-category"} required style={{"width":"100%","background":"var(--bg-secondary)","border":"1px solid var(--border-color)","color":"var(--text-primary)","borderRadius":"var(--radius-sm)","paddingTop":"0.5rem","paddingRight":"0.5rem","paddingBottom":"0.5rem","paddingLeft":"0.5rem","padding":"0.5rem","outline":"none"}}>
<option value={"Dépôt / Retrait"}>{"Dépôt / Retrait"}</option>

<option value={"Marché / Résolution"}>{"Marché / Résolution"}</option>

<option value={"Bug Technique"}>{"Bug Technique"}</option>

<option value={"Autre"}>{"Autre"}</option>
</select>
</div>

<div className={"form-group"}>
<label htmlFor={"new-ticket-subject"}>{"Sujet"}</label>

<input type={"text"} id={"new-ticket-subject"} placeholder={"Résumé bref de votre demande"} required />
</div>

<div className={"form-group"}>
<label htmlFor={"new-ticket-desc"}>{"Description (Premier message)"}</label>

<textarea id={"new-ticket-desc"} rows={4} placeholder={"Détaillez votre problème..."} required style={{"width":"100%","background":"var(--bg-secondary)","border":"1px solid var(--border-color)","color":"var(--text-primary)","borderRadius":"var(--radius-sm)","paddingTop":"0.5rem","paddingRight":"0.5rem","paddingBottom":"0.5rem","paddingLeft":"0.5rem","padding":"0.5rem","outline":"none","fontSize":"0.85rem","boxSizing":"border-box"}} defaultValue={""} />
</div>

<div className={"modal-actions-layout"}>
<button type={"button"} className={"btn btn--outline"} id={"new-ticket-cancel"}>{"Annuler"}</button>

<button type={"submit"} className={"btn btn--primary"}>{"Soumettre le Ticket"}</button>
</div>
</form>
</div>
</div>);
});
