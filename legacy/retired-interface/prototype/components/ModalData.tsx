import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ModalData() {
 return (<div className={"modal-backdrop"} id={"modal-data"}>
<div className={"modal-card"} style={{"maxWidth":"600px"}}>
<button type={"button"} className={"modal-close-btn"} id={"data-close"}>{"×"}</button>

<h3 className={"modal-title"} data-i18n={"data_title"}>{"Gestion des Données Personnelles"}</h3>

<p className={"modal-subtitle"} data-i18n={"data_subtitle"}>{"Contrôle de vos informations locales"}</p>

<div className={"legal-content"} data-i18n-html={"data_content"} style={{"maxHeight":"400px","overflowY":"auto","fontSize":"0.85rem","color":"var(--text-secondary)","lineHeight":"1.6","paddingRight":"var(--space-xs)","borderTop":"1px solid var(--border-color)","paddingTop":"var(--space-md)"}}>
<h4 style={{"color":"var(--text-primary)","marginTop":"var(--space-sm)","fontSize":"0.95rem"}}>{"1. Droit d'accès et de rectification"}</h4>

<p style={{"marginBottom":"var(--space-sm)"}}>{"Conformément aux réglementations sur la protection des données (RGPD / lois africaines sur la protection de la vie privée), vous disposez du contrôle total sur vos données. Comme celles-ci sont stockées localement, vous pouvez les modifier directement en effectuant des recharges, retraits ou en réinitialisant le simulateur depuis le panneau d'administration (Backoffice)."}</p>

<h4 style={{"color":"var(--text-primary)","marginTop":"var(--space-sm)","fontSize":"0.95rem"}}>{"2. Cookies et traceurs"}</h4>

<p style={{"marginBottom":"var(--space-sm)"}}>{"Cette application n'utilise aucun cookie tiers ou traceur publicitaire. Seul le stockage local technique est exploité pour mémoriser l'état de votre portefeuille d'une session à l'autre."}</p>
</div>
</div>
</div>);
});
