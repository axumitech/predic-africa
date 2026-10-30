import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ViewDashboard() {
 return (<section id={"view-dashboard"} className={"view-section active"}>




<div className={"analytics-summary"}>
<div className={"stat-card"}>
<div className={"stat-card__icon text--green"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"}>
<path d={"M12 8C9.79086 8 8 9.79086 8 12C8 14.2091 9.79086 16 12 16C14.2091 16 16 14.2091 16 12C16 9.79086 14.2091 8 12 8Z"} stroke={"currentColor"} strokeWidth={"2"}></path>

<path d={"M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2Z"} stroke={"currentColor"} strokeWidth={"2"}></path>
</svg>
</div>

<div className={"stat-card__info"}>
<span className={"stat-title"}>{"Volume Global"}</span>

<span className={"stat-value"} id={"stat-total-volume"}>{"12 450 000 FCFA"}</span>
</div>
</div>

<div className={"stat-card"}>
<div className={"stat-card__icon text--gold"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"}>
<path d={"M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>
</div>

<div className={"stat-card__info"}>
<span className={"stat-title"}>{"Positions Actives"}</span>

<span className={"stat-value"} id={"stat-active-bets"}>{"384"}</span>
</div>
</div>

<div className={"stat-card"}>
<div className={"stat-card__icon text--orange"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"}>
<path d={"M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>

<path d={"M19.4 15C20.3 15.6 20.3 17 19.4 17.6L18.4 18.4C17.5 19 16.1 18.3 16.1 17.3V16.7C16.1 15.6 15.2 14.7 14.1 14.7H13.5C12.4 14.7 11.5 15.6 11.5 16.7V17.3C11.5 18.3 10.1 19 9.2 18.4L8.2 17.6C7.3 17 7.3 15.6 8.2 15L9.2 14.2C10.1 13.6 10.1 12.2 9.2 11.6L8.2 10.8C7.3 10.2 7.3 8.8 8.2 8.2L9.2 7.4C10.1 6.8 11.5 7.5 11.5 8.5V9.1C11.5 10.2 12.4 11.1 13.5 11.1H14.1C15.2 11.1 16.1 10.2 16.1 9.1V8.5C16.1 7.5 17.5 6.8 18.4 7.4L19.4 8.2C20.3 8.8 20.3 10.2 19.4 10.8L18.4 11.6C17.5 12.2 17.5 13.6 18.4 14.2L19.4 15Z"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>
</div>

<div className={"stat-card__info"}>
<span className={"stat-title"}>{"Investisseurs payés"}</span>

<span className={"stat-value"} id={"stat-paid-winners"}>{"8 920 000 FCFA"}</span>
</div>
</div>
</div>



<div className={"categories-bar"}>
<button className={"category-pill active"} data-category={"all"}>{"Toutes"}</button>

<button className={"category-pill"} data-category={"sports"}>{"Sports"}</button>

<button className={"category-pill"} data-category={"crypto"}>{"Crypto"}</button>

<button className={"category-pill"} data-category={"esports"}>{"Esports"}</button>

<button className={"category-pill"} data-category={"finance"}>{"Finance"}</button>

<button className={"category-pill"} data-category={"geopolitics"}>{"Géopolitique"}</button>

<button className={"category-pill"} data-category={"tech"}>{"Tech"}</button>

<button className={"category-pill"} data-category={"culture"}>{"Culture"}</button>

<button className={"category-pill"} data-category={"music"}>{"Musique"}</button>

<button className={"category-pill"} data-category={"economy"}>{"Économie"}</button>

<button className={"category-pill"} data-category={"weather"}>{"Météo"}</button>

<button className={"category-pill"} data-category={"mentions"}>{"Mentions"}</button>

<button className={"category-pill"} data-category={"elections"}>{"Élections"}</button>

<button className={"category-pill"} data-category={"art"}>{"Art"}</button>
</div>



<div className={"predictions-grid"} id={"predictions-catalog"}>


<div className={"grid-loading"}>{"Chargement des marchés prédictifs..."}</div>
</div>

<div className={"load-more-container"} style={{"textAlign":"center","marginTop":"var(--space-xl)","paddingBottom":"var(--space-xl)"}}>
<button className={"btn btn--outline"} id={"btn-load-more"} style={{"display":"none","width":"100%","maxWidth":"300px","marginTop":"0px","marginRight":"auto","marginBottom":"0px","marginLeft":"auto","margin":"0px auto"}}>{"Afficher plus de marchés"}</button>
</div>
</section>);
});
