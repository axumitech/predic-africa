import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ModalCgu() {
 return (<div className={"modal-backdrop"} id={"modal-cgu"}>
<div className={"modal-card"} style={{"maxWidth":"600px"}}>
<button type={"button"} className={"modal-close-btn"} id={"cgu-close"}>{"×"}</button>

<h3 className={"modal-title"} data-i18n={"cgu_title"}>{"Conditions Générales d'Utilisation (CGU)"}</h3>

<p className={"modal-subtitle"} data-i18n={"cgu_subtitle"}>{"Dernière mise à jour : Juin 2026"}</p>

<div className={"legal-content"} data-i18n-html={"cgu_content"} style={{"maxHeight":"400px","overflowY":"auto","fontSize":"0.85rem","color":"var(--text-secondary)","lineHeight":"1.6","paddingRight":"var(--space-xs)","borderTop":"1px solid var(--border-color)","paddingTop":"var(--space-md)"}}>
<h4 style={{"color":"var(--text-primary)","marginTop":"var(--space-sm)","fontSize":"0.95rem"}}>{"1. Objet de la plateforme"}</h4>

<p style={{"marginBottom":"var(--space-sm)"}}>{"PredicAfrica est une plateforme de simulation pédagogique représentant une bourse d'opinions et de prédictions sur l'actualité africaine. L'ensemble des transactions, soldes de portefeuilles, cotes et gains sont fictifs et n'ont aucune valeur monétaire ou juridique réelle."}</p>

<h4 style={{"color":"var(--text-primary)","marginTop":"var(--space-sm)","fontSize":"0.95rem"}}>{"2. Utilisation du service"}</h4>

<p style={{"marginBottom":"var(--space-sm)"}}>{"L'utilisation de la plateforme est réservée à des fins de divertissement et de démonstration technique. Tout abus ou tentative de manipulation des scripts locaux (localStorage) à des fins de fraude n'affecte que l'instance locale de l'utilisateur."}</p>

<h4 style={{"color":"var(--text-primary)","marginTop":"var(--space-sm)","fontSize":"0.95rem"}}>{"3. Limitation de responsabilité"}</h4>

<p style={{"marginBottom":"var(--space-sm)"}}>{"Les concepteurs de PredicAfrica ne sauraient être tenus responsables de toute mauvaise interprétation du caractère financier de cette application, celle-ci étant strictement un prototype (MVP)."}</p>
</div>
</div>
</div>);
});
