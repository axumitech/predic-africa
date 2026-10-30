import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ViewCustomerSupport() {
 return (<section id={"view-customer-support"} className={"view-section"}>
<div className={"section-header"}>
<div>
<h2 className={"section-title"}>{"Support"}</h2>

<p className={"section-desc"}>{"Suivi de la satisfaction, traitement des requêtes utilisateurs et tableau de bord des performances d'assistance."}</p>
</div>
</div>



<div className={"creator-card"}>
<div className={"card-header"} style={{"display":"flex","justifyContent":"space-between","alignItems":"center","marginBottom":"var(--space-md)","borderBottom":"1px solid var(--border-color)","paddingBottom":"var(--space-sm)","flexWrap":"wrap","gap":"0.5rem"}}>
<div>
<h3 className={"form-subtitle"} style={{"marginTop":"0px","marginRight":"0px","marginBottom":"0px","marginLeft":"0px","margin":"0px","paddingTop":"0px","paddingRight":"0px","paddingBottom":"0px","paddingLeft":"0px","padding":"0px","borderTopWidth":"medium","borderRightWidth":"medium","borderBottomWidth":"medium","borderLeftWidth":"medium","borderTopStyle":"none","borderRightStyle":"none","borderBottomStyle":"none","borderLeftStyle":"none","borderTopColor":"currentcolor","borderRightColor":"currentcolor","borderBottomColor":"currentcolor","borderLeftColor":"currentcolor","borderWidth":"medium","borderStyle":"none","borderColor":"currentcolor","borderTop":"medium","borderRight":"medium","borderBottom":"medium","borderLeft":"medium","border":"medium","borderImage":"none"}}>{"Vos Tickets de Support"}</h3>

<p className={"text--muted font-sm"} style={{"marginTop":"2px"}}>{"Historique de vos requêtes et assistance technique."}</p>
</div>

<button className={"btn btn--primary"} id={"btn-open-new-ticket"}><i className={"fa-solid fa-plus"}></i>
{" Nouveau Ticket"}</button>
</div>

<div className={"table-container"} style={{"maxHeight":"400px","overflow":"auto","width":"100%","boxSizing":"border-box"}}>
<table className={"transactions-table"}>
<thead>
<tr>
<th>{"ID Ticket"}</th>

<th>{"Sujet"}</th>

<th>{"Catégorie"}</th>

<th>{"Statut"}</th>

<th>{"Dernière MAJ"}</th>

<th>{"Action"}</th>
</tr>
</thead>

<tbody id={"user-tickets-tbody"}>

</tbody>
</table>
</div>
</div>
</section>);
});
