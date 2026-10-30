import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ModalPrivacy() {
 return (<div className={"modal-backdrop"} id={"modal-privacy"}>
<div className={"modal-card"} style={{"maxWidth":"600px"}}>
<button type={"button"} className={"modal-close-btn"} id={"privacy-close"}>{"×"}</button>

<h3 className={"modal-title"} data-i18n={"privacy_title"}>{"Politique de Confidentialité"}</h3>

<p className={"modal-subtitle"} data-i18n={"privacy_subtitle"}>{"Respect de votre vie privée"}</p>

<div className={"legal-content"} data-i18n-html={"privacy_content"} style={{"maxHeight":"400px","overflowY":"auto","fontSize":"0.85rem","color":"var(--text-secondary)","lineHeight":"1.6","paddingRight":"var(--space-xs)","borderTop":"1px solid var(--border-color)","paddingTop":"var(--space-md)"}}>
<h4 style={{"color":"var(--text-primary)","marginTop":"var(--space-sm)","fontSize":"0.95rem"}}>{"1. Collecte des données"}</h4>

<p style={{"marginBottom":"var(--space-sm)"}}>{"PredicAfrica ne collecte aucune donnée personnelle sur ses serveurs. L'ensemble des données d'utilisation (solde de portefeuille, positions achetées, historique des recharges et retraits) est stocké localement et exclusivement dans le navigateur de l'utilisateur via le mécanisme de "}
<code>{"localStorage"}</code>
{"."}</p>

<h4 style={{"color":"var(--text-primary)","marginTop":"var(--space-sm)","fontSize":"0.95rem"}}>{"2. Transmission des données"}</h4>

<p style={{"marginBottom":"var(--space-sm)"}}>{"Aucune donnée n'est transmise à des tiers ou hébergée à l'extérieur de votre appareil. En vidant le cache de votre navigateur ou en effaçant les données de site, l'ensemble de votre historique sera définitivement supprimé."}</p>
</div>
</div>
</div>);
});
