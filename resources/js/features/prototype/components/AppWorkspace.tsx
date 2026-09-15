import { memo } from 'react';
import ViewDashboard from './ViewDashboard';
import ViewMyBets from './ViewMyBets';
import ViewTransactions from './ViewTransactions';
import ViewCreator from './ViewCreator';
import ViewBackoffice from './ViewBackoffice';
import ViewUsersManagement from './ViewUsersManagement';
import ViewCustomerSupport from './ViewCustomerSupport';

// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function AppWorkspace() {
 return (<div id={"app-workspace"} style={{"display":"none"}}>
<div className={"app-container"}>


<aside className={"sidebar"}>
<div className={"sidebar__brand"}>
<img src="/HOR_WG.png" alt={"Wegame Logo"} className={"brand-img"} style={{"height":"40px","width":"auto"}} />
</div>



<div className={"country-selector"} id={"sidebar-country-selector"}>
<label htmlFor={"user-country"} className={"country-selector__label"}>{"Région :"}</label>

<select id={"user-country"} className={"country-selector__select"} defaultValue={"SEN"}>
<option value={"SEN"}>{"Sénégal (XOF)"}</option>

<option value={"CIV"}>{"Côte d'Ivoire (XOF)"}</option>

<option value={"CMR"}>{"Cameroun (XAF)"}</option>

<option value={"COD"}>{"RDC (CDF)"}</option>

<option value={"COG"}>{"Congo Brazzaville (XAF)"}</option>

<option value={"GAB"}>{"Gabon (XAF)"}</option>

<option value={"BEN"}>{"Bénin (XOF)"}</option>

<option value={"TGO"}>{"Togo (XOF)"}</option>
</select>
</div>



<div className={"wallet-widget"} id={"sidebar-wallet-widget"}>
<div className={"wallet-widget__header"}>
<span className={"wallet-widget__title"}>{"Mon Portefeuille"}</span>

<span className={"wallet-widget__badge"}>{"Compte MVP"}</span>
</div>

<div className={"wallet-widget__balance"}>
<span className={"balance-value"} id={"user-balance"}>{"15 000"}</span>

<span className={"balance-currency"}>{"FCFA"}</span>
</div>

<div className={"wallet-widget__actions"} style={{"display":"grid","gridTemplateColumns":"1fr 1fr","gap":"var(--space-xs)"}}>
<button className={"btn btn--primary btn--block"} id={"btn-recharge-trigger"} style={{"padding":"0.5rem var(--space-xs)","fontSize":"0.8rem"}}>{"\n                        Recharger\n                    "}</button>

<button className={"btn btn--outline btn--block"} id={"btn-withdraw-trigger"} style={{"padding":"0.5rem var(--space-xs)","fontSize":"0.8rem","borderColor":"var(--accent-primary)"}}>{"\n                        Retirer\n                    "}</button>
</div>
</div>



<nav className={"nav-menu"}>
<a href={"#dashboard"} className={"nav-menu__item active"} data-view={"dashboard"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"}>
<rect x={"3"} y={"3"} width={"7"} height={"9"} rx={"1"} stroke={"currentColor"} strokeWidth={"2"} strokeLinejoin={"round"}></rect>

<rect x={"14"} y={"3"} width={"7"} height={"5"} rx={"1"} stroke={"currentColor"} strokeWidth={"2"} strokeLinejoin={"round"}></rect>

<rect x={"14"} y={"12"} width={"7"} height={"9"} rx={"1"} stroke={"currentColor"} strokeWidth={"2"} strokeLinejoin={"round"}></rect>

<rect x={"3"} y={"16"} width={"7"} height={"5"} rx={"1"} stroke={"currentColor"} strokeWidth={"2"} strokeLinejoin={"round"}></rect>
</svg>

<span>{"Tous les marchés"}</span>
</a>

<a href={"#my-bets"} className={"nav-menu__item"} data-view={"my-bets"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"}>
<path d={"M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15M9 5C9 6.10457 9.89543 7 11 7H13C14.1046 7 15 6.10457 15 5M9 5C9 3.89543 9.89543 3 11 3H13C14.1046 3 15 3.89543 15 5M12 12H15M12 16H15M9 12H9.01M9 16H9.01"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>

<span>{"Mes Positions"}</span>

<span className={"badge badge--accent"} id={"active-bets-count"}>{"0"}</span>
</a>

<a href={"#transactions"} className={"nav-menu__item"} data-view={"transactions"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"}>
<path d={"M12 8V12L15 15M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>

<span>{"Transactions"}</span>
</a>

<a href={"#creator"} className={"nav-menu__item"} data-view={"creator"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"}>
<path d={"M12 4V20M20 12H4"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>

<span>{"Créer un Marché"}</span>
</a>

<a href={"#backoffice"} className={"nav-menu__item"} data-view={"backoffice"} id={"nav-item-backoffice"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"}>
<path d={"M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>

<path d={"M15 12a3 3 0 11-6 0 3 3 0 016 0z"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>

<span>{"Tous les marchés"}</span>
</a>

<a href={"#users-management"} className={"nav-menu__item"} data-view={"users-management"} id={"nav-item-users"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"}>
<path d={"M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>

<circle cx={"9"} cy={"7"} r={"4"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></circle>

<path d={"M23 21v-2a4 4 0 0 0-3-3.87"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>

<path d={"M16 3.13a4 4 0 0 1 0 7.75"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>

<span>{"Gestion Utilisateurs"}</span>
</a>

<a href={"#customer-support"} className={"nav-menu__item"} data-view={"customer-support"} id={"nav-item-support"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"}>
<path d={"M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>

<path d={"M8 10h8M8 14h5"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>

<span>{"Support Client"}</span>

<span className={"badge badge--accent"} id={"support-open-tickets-badge"}>{"2"}</span>
</a>
</nav>



<div className={"sidebar__profile"}>
<div className={"profile-avatar"}>{"AD"}</div>

<div className={"profile-info"}>
<div className={"profile-name"} id={"user-profile-name"}>{"Ablaye Diop"}</div>

<div className={"profile-location"}>{"Dakar, Sénégal"}</div>
</div>

<button className={"btn-text-action btn-logout"} id={"btn-logout-trigger"} style={{"marginLeft":"auto","fontSize":"0.75rem","color":"var(--accent-red)"}}>{"\n                    Quitter\n                "}</button>
</div>
</aside>



<main className={"main-panel"}>


<header className={"app-header"}>




<div className={"app-header__actions"}>
<div className={"status-indicator"}>
<span className={"pulse-dot"}></span>

<span className={"status-text"}>{"Simulateur Actif"}</span>
</div>

<div className={"notification-bell"} id={"btn-notifications"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"}>
<path d={"M15 17H20L18.5951 15.5951C17.5841 14.5841 17 13.213 17 11.783V8.78289C17 6.0243 14.9925 3.73351 12.3162 3.16113C12.1932 2.49079 11.6053 2 10.9 2C10.1947 2 9.60683 2.49079 9.48376 3.16113C6.80753 3.73351 4.8 6.0243 4.8 8.78289V11.783C4.8 13.213 4.2159 14.5841 3.2049 15.5951L1.8 17H20M15 17V18C15 20.2091 13.2091 22 11 22C8.79086 22 7 20.2091 7 18V17H15Z"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>

<span className={"notification-badge"}>{"3"}</span>



<div className={"notification-dropdown"} id={"notification-dropdown"}>
<div className={"notification-dropdown__header"}>
<h4>{"Notifications"}</h4>

<button className={"btn-text-action"} style={{"fontSize":"0.8rem","color":"var(--accent-primary)"}}>{"Tout marquer comme lu"}</button>
</div>

<div className={"notification-list"}>
<div className={"notification-item unread"}>
<div className={"notification-icon"} style={{"color":"var(--accent-primary)"}}><i className={"fa-solid fa-check-circle"}></i></div>

<div className={"notification-content"}>
<p>{"Votre prédiction sur "}
<strong>{"Élections Sénégal"}</strong>
{" a été validée."}</p>

<span className={"notification-time"}>{"Il y a 2 heures"}</span>
</div>
</div>

<div className={"notification-item unread"}>
<div className={"notification-icon"} style={{"color":"var(--accent-green)"}}><i className={"fa-solid fa-money-bill-wave"}></i></div>

<div className={"notification-content"}>
<p>{"Gains de "}
<strong>{"15 000 FCFA"}</strong>
{" crédités sur votre portefeuille."}</p>

<span className={"notification-time"}>{"Il y a 5 heures"}</span>
</div>
</div>

<div className={"notification-item"}>
<div className={"notification-icon"} style={{"color":"var(--text-secondary)"}}><i className={"fa-solid fa-bullhorn"}></i></div>

<div className={"notification-content"}>
<p>{"Nouveau marché : "}
<strong>{"Coupe d'Afrique des Nations 2026"}</strong>
{" disponible !"}</p>

<span className={"notification-time"}>{"Hier"}</span>
</div>
</div>
</div>

<div className={"notification-dropdown__footer"}>
<a href={"#"}>{"Voir toutes les notifications"}</a>
</div>
</div>
</div>
</div>
</header>



<div className={"view-content"}>


<ViewDashboard />



<ViewMyBets />



<ViewTransactions />



<ViewCreator />



<ViewBackoffice />



<ViewUsersManagement />



<ViewCustomerSupport />
</div>
</main>
</div>
</div>);
});
