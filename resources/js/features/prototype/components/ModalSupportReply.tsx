import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ModalSupportReply() {
 return (<div className={"modal-backdrop"} id={"modal-support-reply"}>
<div className={"modal-card"} style={{"maxWidth":"650px","width":"95%","maxHeight":"90vh","display":"flex","flexDirection":"column"}}>
<button type={"button"} className={"modal-close-btn"} id={"support-modal-close"}>{"×"}</button>

<div className={"modal-header-block"} style={{"borderBottom":"1px solid var(--border-color)","paddingBottom":"var(--space-sm)","marginBottom":"var(--space-sm)"}}>
<div style={{"display":"flex","justifyContent":"space-between","alignItems":"center","flexWrap":"wrap","gap":"8px"}}>
<h3 className={"modal-title"} id={"support-modal-title"} style={{"marginTop":"0px","marginRight":"0px","marginBottom":"0px","marginLeft":"0px","margin":"0px"}}>{"Ticket de Support"}</h3>

<span id={"support-modal-status-badge"} className={"status-txt-badge text--gold"} style={{"border":"1px solid var(--accent-gold)"}}>{"En cours"}</span>
</div>

<div style={{"marginTop":"6px","fontSize":"0.8rem","fontWeight":"600","color":"var(--text-primary)"}} id={"support-modal-subject-display"}>{"\n                    Sujet: Chargement...\n                "}</div>
</div>



<div id={"support-modal-thread"} style={{"flexGrow":"1","flexShrink":"1","flexBasis":"0%","flex":"1 1 0%","minHeight":"200px","maxHeight":"320px","overflowY":"auto","background":"var(--bg-tertiary)","border":"1px solid var(--border-color)","borderRadius":"var(--radius-md)","padding":"var(--space-md)","marginBottom":"var(--space-md)","display":"flex","flexDirection":"column","gap":"var(--space-sm)"}}>

</div>



<form id={"form-support-reply"} style={{"display":"flex","flexDirection":"column","gap":"var(--space-sm)"}}>
<input type={"hidden"} id={"support-modal-ticket-id"} />

<div className={"form-group"} style={{"marginTop":"0px","marginRight":"0px","marginBottom":"0px","marginLeft":"0px","margin":"0px"}}>
<textarea id={"support-modal-response"} rows={3} placeholder={"Saisissez votre message..."} required style={{"width":"100%","background":"var(--bg-secondary)","border":"1px solid var(--border-color)","color":"var(--text-primary)","borderRadius":"var(--radius-sm)","paddingTop":"0.5rem","paddingRight":"0.5rem","paddingBottom":"0.5rem","paddingLeft":"0.5rem","padding":"0.5rem","outline":"none","fontSize":"0.85rem","boxSizing":"border-box"}} defaultValue={""} />
</div>

<div style={{"display":"flex","justifyContent":"space-between","alignItems":"center","gap":"var(--space-md)","flexWrap":"wrap"}}>
<div style={{"display":"flex","alignItems":"center","gap":"8px"}}>
<label htmlFor={"support-modal-status"} style={{"fontSize":"0.75rem","fontWeight":"600","color":"var(--text-secondary)"}}>{"Statut du Ticket :"}</label>

<select id={"support-modal-status"} required style={{"background":"var(--bg-secondary)","border":"1px solid var(--border-color)","color":"var(--text-primary)","borderRadius":"var(--radius-sm)","paddingTop":"0.35rem","paddingRight":"0.5rem","paddingBottom":"0.35rem","paddingLeft":"0.5rem","padding":"0.35rem 0.5rem","fontSize":"0.75rem","outline":"none"}}>
<option value={"En cours"}>{"En cours"}</option>

<option value={"Résolu"}>{"Résolu & Clôturé"}</option>
</select>
</div>

<button type={"submit"} className={"btn btn--primary"} style={{"paddingTop":"0.5rem","paddingRight":"1.25rem","paddingBottom":"0.5rem","paddingLeft":"1.25rem","padding":"0.5rem 1.25rem","fontSize":"0.85rem","display":"inline-flex","alignItems":"center","gap":"6px"}}>
<svg viewBox={"0 0 24 24"} fill={"none"} width={"14"} height={"14"} stroke={"currentColor"} strokeWidth={"2"}>
<line x1={"22"} y1={"2"} x2={"11"} y2={"13"}></line>

<polygon points={"22 2 15 22 11 13 2 9 22 2"}></polygon>
</svg>

<span>{"Envoyer le Message"}</span>

<span>{"Envoyer"}</span>
</button>
</div>
</form>
</div>
</div>);
});
