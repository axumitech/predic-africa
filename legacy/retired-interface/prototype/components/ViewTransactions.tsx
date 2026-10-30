import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ViewTransactions() {
 return (<section id={"view-transactions"} className={"view-section"}>
<div className={"section-header"}>
<div>
<h2 className={"section-title"}>{"Historique des Transactions"}</h2>

<p className={"section-desc"}>{"Consultez l'historique complet de vos dépôts par Mobile Money et de vos investissements de marché."}</p>
</div>
</div>

<div className={"table-container"}>
<table className={"transactions-table"}>
<thead>
<tr>
<th>{"Référence / ID"}</th>

<th>{"Date"}</th>

<th>{"Type"}</th>

<th>{"Opérateur / Canal"}</th>

<th>{"Montant"}</th>

<th>{"Statut"}</th>
</tr>
</thead>

<tbody id={"transactions-log-tbody"}>

</tbody>
</table>

<div className={"empty-state"} id={"transactions-empty-state"}>{"Aucune transaction enregistrée."}</div>
</div>
</section>);
});
