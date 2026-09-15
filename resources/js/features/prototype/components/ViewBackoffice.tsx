import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ViewBackoffice() {
 return (<section id={"view-backoffice"} className={"view-section"}>
<div className={"section-header"} style={{"display":"flex","flexDirection":"column","alignItems":"flex-start","gap":"var(--space-xs)","marginBottom":"var(--space-md)"}}>
<h2 className={"section-title"}>{"Tableau de Bord d'Administration"}</h2>

<p className={"section-desc"}>{"Supervision globale de la plateforme, gestion multi-pays, arbitrage des marchés et monitoring des flux financiers."}</p>



<div className={"admin-country-toolbar"} style={{"display":"flex","alignItems":"center","gap":"0.5rem","background":"var(--bg-tertiary)","paddingTop":"0.5rem","paddingRight":"0.85rem","paddingBottom":"0.5rem","paddingLeft":"0.85rem","padding":"0.5rem 0.85rem","borderRadius":"var(--radius-md)","border":"1px solid var(--border-color)","marginTop":"6px","alignSelf":"flex-start"}}>
<label htmlFor={"admin-country-filter"} style={{"fontSize":"0.8125rem","fontWeight":"600","color":"var(--text-secondary)","whiteSpace":"nowrap","display":"inline-flex","alignItems":"center","gap":"4px"}}>
<svg viewBox={"0 0 24 24"} fill={"none"} width={"15"} height={"15"} stroke={"currentColor"} strokeWidth={"2"}>
<circle cx={"12"} cy={"12"} r={"10"}></circle>

<path d={"M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"}></path>
</svg>

<span>{"Filtre par Pays :"}</span>
</label>

<select id={"admin-country-filter"} style={{"background":"var(--bg-secondary)","border":"1px solid var(--border-color)","color":"var(--text-primary)","borderRadius":"var(--radius-sm)","paddingTop":"0.4rem","paddingRight":"0.6rem","paddingBottom":"0.4rem","paddingLeft":"0.6rem","padding":"0.4rem 0.6rem","fontSize":"0.8125rem","fontWeight":"600","cursor":"pointer","outline":"none"}}>
<option value={"ALL"}>{"Tous les pays (Global)"}</option>

<option value={"SEN"}>{"Sénégal (XOF)"}</option>

<option value={"CIV"}>{"Côte d'Ivoire (XOF)"}</option>

<option value={"CMR"}>{"Cameroun (XAF)"}</option>

<option value={"MLI"}>{"Mali (XOF)"}</option>

<option value={"COD"}>{"RDC (CDF)"}</option>

<option value={"COG"}>{"Congo (XAF)"}</option>

<option value={"GAB"}>{"Gabon (XAF)"}</option>

<option value={"BEN"}>{"Bénin (XOF)"}</option>

<option value={"TGO"}>{"Togo (XOF)"}</option>

<option value={"GHS"}>{"Ghana (GHS)"}</option>
</select>
</div>
</div>



<div className={"analytics-summary"} style={{"display":"grid","gridTemplateColumns":"repeat(auto-fit, minmax(180px, 1fr))","gap":"var(--space-md)","marginBottom":"var(--space-lg)"}}>
<div className={"stat-card"}>
<div className={"stat-card__info"}>
<span className={"stat-title"}>{"Volume Négocié"}</span>

<span className={"stat-value"} id={"admin-total-volume"}>{"0 XOF"}</span>
</div>
</div>

<div className={"stat-card"}>
<div className={"stat-card__info"}>
<span className={"stat-title"}>{"Crédits (Dépôts)"}</span>

<span className={"stat-value text--primary"} id={"admin-total-deposits"}>{"0 XOF"}</span>
</div>
</div>

<div className={"stat-card"}>
<div className={"stat-card__info"}>
<span className={"stat-title"}>{"Retraits Portefeuille"}</span>

<span className={"stat-value text--red"} id={"admin-total-withdrawals"}>{"0 XOF"}</span>
</div>
</div>

<div className={"stat-card"}>
<div className={"stat-card__info"}>
<span className={"stat-title"}>{"Tracking Gains Distribués"}</span>

<span className={"stat-value text--gold"} id={"admin-total-gains"}>{"0 XOF"}</span>
</div>
</div>

<div className={"stat-card"}>
<div className={"stat-card__info"}>
<span className={"stat-title"}>{"Frais Collectés (5%)"}</span>

<span className={"stat-value text--primary"} id={"admin-platform-fees"}>{"0 XOF"}</span>
</div>
</div>
</div>

<div className={"admin-grid"} style={{"display":"grid","gridTemplateColumns":"1fr","gap":"var(--space-lg)","marginTop":"var(--space-lg)"}}>


<div className={"creator-card"}>
<div className={"card-header"} style={{"display":"flex","justifyContent":"space-between","alignItems":"center","marginBottom":"var(--space-md)","borderBottom":"1px solid var(--border-color)","paddingBottom":"var(--space-sm)"}}>
<h3 className={"form-subtitle"} style={{"marginTop":"0px","marginRight":"0px","marginBottom":"0px","marginLeft":"0px","margin":"0px","paddingTop":"0px","paddingRight":"0px","paddingBottom":"0px","paddingLeft":"0px","padding":"0px","borderTopWidth":"medium","borderRightWidth":"medium","borderBottomWidth":"medium","borderLeftWidth":"medium","borderTopStyle":"none","borderRightStyle":"none","borderBottomStyle":"none","borderLeftStyle":"none","borderTopColor":"currentcolor","borderRightColor":"currentcolor","borderBottomColor":"currentcolor","borderLeftColor":"currentcolor","borderWidth":"medium","borderStyle":"none","borderColor":"currentcolor","borderTop":"medium","borderRight":"medium","borderBottom":"medium","borderLeft":"medium","border":"medium","borderImage":"none"}}>{"Clôture & Résolution des Marchés"}</h3>

<div className={"form-group"} style={{"flexGrow":"0","flexShrink":"1","flexBasis":"0%","flex":"0 1 0%","minWidth":"140px","marginTop":"0px","marginRight":"0px","marginBottom":"0px","marginLeft":"0px","margin":"0px"}}>
<label className={"switch-container"} style={{"display":"flex","alignItems":"center","gap":"8px","fontSize":"0.75rem","cursor":"pointer"}}>
<input type={"checkbox"} id={"admin-toggle-sim-odds"} defaultChecked style={{"width":"auto"}} />

<span>{"Simulateur Cotes Live"}</span>
</label>
</div>
</div>

<div className={"table-container"} style={{"maxHeight":"350px","overflow":"auto","width":"100%","boxSizing":"border-box"}}>
<table className={"transactions-table"}>
<thead>
<tr>
<th>{"Question du Marché"}</th>

<th>{"Région"}</th>

<th>{"Volume"}</th>

<th>{"Statut"}</th>

<th>{"Action Admin"}</th>
</tr>
</thead>

<tbody id={"admin-markets-tbody"}>

</tbody>
</table>
</div>
</div>



<div className={"creator-card"} style={{"marginTop":"var(--space-lg)"}}>
<div className={"card-header"} style={{"display":"flex","justifyContent":"space-between","alignItems":"center","marginBottom":"var(--space-md)","borderBottom":"1px solid var(--border-color)","paddingBottom":"var(--space-sm)","flexWrap":"wrap","gap":"0.5rem"}}>
<div>
<h3 className={"form-subtitle"} style={{"marginTop":"0px","marginRight":"0px","marginBottom":"0px","marginLeft":"0px","margin":"0px","paddingTop":"0px","paddingRight":"0px","paddingBottom":"0px","paddingLeft":"0px","padding":"0px","borderTopWidth":"medium","borderRightWidth":"medium","borderBottomWidth":"medium","borderLeftWidth":"medium","borderTopStyle":"none","borderRightStyle":"none","borderBottomStyle":"none","borderLeftStyle":"none","borderTopColor":"currentcolor","borderRightColor":"currentcolor","borderBottomColor":"currentcolor","borderLeftColor":"currentcolor","borderWidth":"medium","borderStyle":"none","borderColor":"currentcolor","borderTop":"medium","borderRight":"medium","borderBottom":"medium","borderLeft":"medium","border":"medium","borderImage":"none"}}>{"Monitoring Financier Joueurs"}</h3>

<p className={"text--muted font-sm"} style={{"marginTop":"2px"}}>{"Suivi en temps réel des recharges Mobile Money, retraits et gains distribués."}</p>
</div>

<div className={"admin-monitoring-tabs"} style={{"display":"flex","gap":"6px","background":"var(--bg-tertiary)","paddingTop":"4px","paddingRight":"4px","paddingBottom":"4px","paddingLeft":"4px","padding":"4px","borderRadius":"var(--radius-md)"}}>
<button type={"button"} className={"tab-btn active"} id={"admin-tab-deposits"} data-admin-tab={"deposits"} style={{"fontSize":"0.75rem","paddingTop":"0.4rem","paddingRight":"0.75rem","paddingBottom":"0.4rem","paddingLeft":"0.75rem","padding":"0.4rem 0.75rem","display":"inline-flex","alignItems":"center","gap":"6px"}}>
<svg viewBox={"0 0 24 24"} fill={"none"} width={"14"} height={"14"} stroke={"currentColor"} strokeWidth={"2"}>
<rect x={"1"} y={"4"} width={"22"} height={"16"} rx={"2"} ry={"2"}></rect>

<line x1={"1"} y1={"10"} x2={"23"} y2={"10"}></line>
</svg>

<span>{"Crédits (Dépôts)"}</span>
</button>

<button type={"button"} className={"tab-btn"} id={"admin-tab-withdrawals"} data-admin-tab={"withdrawals"} style={{"fontSize":"0.75rem","paddingTop":"0.4rem","paddingRight":"0.75rem","paddingBottom":"0.4rem","paddingLeft":"0.75rem","padding":"0.4rem 0.75rem","display":"inline-flex","alignItems":"center","gap":"6px"}}>
<svg viewBox={"0 0 24 24"} fill={"none"} width={"14"} height={"14"} stroke={"currentColor"} strokeWidth={"2"}>
<path d={"M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"}></path>
</svg>

<span>{"Retraits Portefeuille"}</span>
</button>

<button type={"button"} className={"tab-btn"} id={"admin-tab-gains"} data-admin-tab={"gains"} style={{"fontSize":"0.75rem","paddingTop":"0.4rem","paddingRight":"0.75rem","paddingBottom":"0.4rem","paddingLeft":"0.75rem","padding":"0.4rem 0.75rem","display":"inline-flex","alignItems":"center","gap":"6px"}}>
<svg viewBox={"0 0 24 24"} fill={"none"} width={"14"} height={"14"} stroke={"currentColor"} strokeWidth={"2"}>
<path d={"M6 9H4.5a2.5 2.5 0 010-5H6M18 9h1.5a2.5 2.5 0 000-5H18M4 22h16M10 14.66V17M14 14.66V17M18 2H6v7a6 6 0 0012 0V2z"}></path>
</svg>

<span>{"Tracking des Gains"}</span>
</button>
</div>
</div>



<div id={"admin-panel-deposits"} className={"admin-monitoring-panel"} style={{"display":"block"}}>
<div className={"table-container"} style={{"maxHeight":"350px","overflow":"auto","width":"100%","boxSizing":"border-box"}}>
<table className={"transactions-table"}>
<thead>
<tr>
<th>{"ID Trans."}</th>

<th>{"Joueur / Compte"}</th>

<th>{"Pays"}</th>

<th>{"Date & Heure"}</th>

<th>{"Canal / Opérateur"}</th>

<th>{"Montant Crédité"}</th>

<th>{"Statut"}</th>
</tr>
</thead>

<tbody id={"admin-deposits-tbody"}>

</tbody>
</table>
</div>
</div>



<div id={"admin-panel-withdrawals"} className={"admin-monitoring-panel"} style={{"display":"none"}}>
<div className={"table-container"} style={{"maxHeight":"350px","overflow":"auto","width":"100%","boxSizing":"border-box"}}>
<table className={"transactions-table"}>
<thead>
<tr>
<th>{"ID Retrait"}</th>

<th>{"Joueur / Bénéficiaire"}</th>

<th>{"Pays"}</th>

<th>{"Date"}</th>

<th>{"Destination Mobile Money"}</th>

<th>{"Montant Retiré"}</th>

<th>{"Statut"}</th>

<th>{"Action Validation"}</th>
</tr>
</thead>

<tbody id={"admin-withdrawals-tbody"}>

</tbody>
</table>
</div>
</div>



<div id={"admin-panel-gains"} className={"admin-monitoring-panel"} style={{"display":"none"}}>
<div className={"table-container"} style={{"maxHeight":"350px","overflow":"auto","width":"100%","boxSizing":"border-box"}}>
<table className={"transactions-table"}>
<thead>
<tr>
<th>{"ID Gains"}</th>

<th>{"Gagnant"}</th>

<th>{"Pays"}</th>

<th>{"Marché / Prédiction"}</th>

<th>{"Choix & Cote"}</th>

<th>{"Mise Initiale"}</th>

<th>{"Gain Généré"}</th>

<th>{"Statut Paiement"}</th>
</tr>
</thead>

<tbody id={"admin-gains-tbody"}>

</tbody>
</table>
</div>
</div>
</div>
</div>
</section>);
});
