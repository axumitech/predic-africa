import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ViewMyBets() {
 return (<section id={"view-my-bets"} className={"view-section"}>
<div className={"section-header"}>
<div>
<h2 className={"section-title"}>{"Mes Positions"}</h2>

<p className={"section-desc"}>{"Gérez vos investissements actifs et encaissez vos rendements sur les marchés clôturés."}</p>
</div>

<div className={"bets-filter-tabs"}>
<button className={"tab-btn active"} data-bet-filter={"active"}>{"En cours"}</button>

<button className={"tab-btn"} data-bet-filter={"resolved"}>{"Clôturés"}</button>
</div>
</div>

<div className={"bets-list"} id={"bets-container"}>


<div className={"empty-state"}>{"Vous n'avez pas encore placé de prédiction."}</div>
</div>
</section>);
});
