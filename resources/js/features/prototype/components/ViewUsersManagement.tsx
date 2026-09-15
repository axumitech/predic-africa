import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ViewUsersManagement() {
 return (<section id={"view-users-management"} className={"view-section"}>
<div className={"section-header"}>
<div>
<h2 className={"section-title"}>{"Gestion des Utilisateurs Système & Rôles"}</h2>

<p className={"section-desc"}>{"Créez et administrez les utilisateurs système (SuperAdmins, Modérateurs, Agents Support, Traders) et gérez leurs privilèges d'accès."}</p>
</div>
</div>



<div className={"creator-card"} style={{"marginBottom":"var(--space-lg)"}}>
<h3 className={"form-subtitle"} style={{"margin":"0 0 var(--space-md) 0","paddingTop":"0px","paddingRight":"0px","paddingBottom":"0px","paddingLeft":"0px","padding":"0px","borderBottom":"medium","borderBottomWidth":"medium","borderBottomStyle":"none","borderBottomColor":"currentcolor","borderTopWidth":"medium","borderRightWidth":"medium","borderLeftWidth":"medium","borderTopStyle":"none","borderRightStyle":"none","borderLeftStyle":"none","borderTopColor":"currentcolor","borderRightColor":"currentcolor","borderLeftColor":"currentcolor","borderWidth":"medium","borderStyle":"none","borderColor":"currentcolor","borderTop":"medium","borderRight":"medium","borderLeft":"medium","border":"medium","borderImage":"none"}}>{"\n                            Création d'un Compte Utilisateur Système\n                        "}</h3>

<form id={"form-create-user"} className={"creator-form"}>
<div className={"form-row"}>
<div className={"form-group"}>
<label htmlFor={"create-user-name"}>{"Nom Complet & Prénom"}</label>

<input type={"text"} id={"create-user-name"} placeholder={"Ex: Mariama Sow"} required />
</div>

<div className={"form-group"}>
<label htmlFor={"create-user-contact"}>{"Adresse Email / Téléphone"}</label>

<input type={"text"} id={"create-user-contact"} placeholder={"mariama.sow@predicafrika.com"} required />
</div>
</div>

<div className={"form-row"}>
<div className={"form-group"}>
<label htmlFor={"create-user-role"}>{"Rôle Système & Permissions"}</label>

<select id={"create-user-role"} required>
<option value={"SuperAdmin"}>{"SuperAdmin (Accès Total)"}</option>

<option value={"Modérateur"}>{"Modérateur de Marchés (Arbitrage & Cotes)"}</option>

<option value={"Agent Support"}>{"Agent Support Client (Gestion des Tickets)"}</option>

<option value={"Trader"}>{"Trader / Joueur (Négociation & Wallet)"}</option>
</select>
</div>

<div className={"form-group"}>
<label htmlFor={"create-user-region"}>{"Région d'Attribution"}</label>

<select id={"create-user-region"} required>
<option value={"ALL"}>{"Toutes les Régions (Global)"}</option>

<option value={"SEN"}>{"Sénégal (SEN)"}</option>

<option value={"CIV"}>{"Côte d'Ivoire (CIV)"}</option>

<option value={"CMR"}>{"Cameroun (CMR)"}</option>

<option value={"MLI"}>{"Mali (MLI)"}</option>

<option value={"COD"}>{"RDC (COD)"}</option>
</select>
</div>

<div className={"form-group"}>
<label htmlFor={"create-user-status"}>{"Statut Initial"}</label>

<select id={"create-user-status"} required>
<option value={"Actif"}>{"Actif (Accès Autorisé)"}</option>

<option value={"Inactif"}>{"Inactif (En attente activation)"}</option>

<option value={"Suspendu"}>{"Suspendu (Accès Bloqué)"}</option>
</select>
</div>
</div>

<div className={"form-actions"} style={{"marginTop":"var(--space-md)"}}>
<button type={"submit"} className={"btn btn--primary"}>{"Créer le Compte Système"}</button>

<button type={"reset"} className={"btn btn--outline"}>{"Réinitialiser"}</button>
</div>
</form>
</div>



<div className={"creator-card"}>
<div className={"card-header"} style={{"display":"flex","justifyContent":"space-between","alignItems":"center","marginBottom":"var(--space-md)","borderBottom":"1px solid var(--border-color)","paddingBottom":"var(--space-sm)","flexWrap":"wrap","gap":"0.5rem"}}>
<h3 className={"form-subtitle"} style={{"marginTop":"0px","marginRight":"0px","marginBottom":"0px","marginLeft":"0px","margin":"0px","paddingTop":"0px","paddingRight":"0px","paddingBottom":"0px","paddingLeft":"0px","padding":"0px","borderTopWidth":"medium","borderRightWidth":"medium","borderBottomWidth":"medium","borderLeftWidth":"medium","borderTopStyle":"none","borderRightStyle":"none","borderBottomStyle":"none","borderLeftStyle":"none","borderTopColor":"currentcolor","borderRightColor":"currentcolor","borderBottomColor":"currentcolor","borderLeftColor":"currentcolor","borderWidth":"medium","borderStyle":"none","borderColor":"currentcolor","borderTop":"medium","borderRight":"medium","borderBottom":"medium","borderLeft":"medium","border":"medium","borderImage":"none"}}>{"Répertoire des Comptes Système"}</h3>

<div className={"app-header__search"} style={{"maxWidth":"250px","marginTop":"0px","marginRight":"0px","marginBottom":"0px","marginLeft":"0px","margin":"0px"}}>
<input type={"text"} id={"search-system-users"} placeholder={"Filtrer nom ou rôle..."} style={{"fontSize":"0.8rem","paddingTop":"0.4rem","paddingRight":"0.75rem","paddingBottom":"0.4rem","paddingLeft":"0.75rem","padding":"0.4rem 0.75rem"}} />
</div>
</div>

<div className={"table-container"} style={{"maxHeight":"400px","overflow":"auto","width":"100%","boxSizing":"border-box"}}>
<table className={"transactions-table"}>
<thead>
<tr>
<th>{"Identifiant / Nom"}</th>

<th>{"Contact / Email"}</th>

<th>{"Rôle Système"}</th>

<th>{"Région"}</th>

<th>{"Dernière Connexion"}</th>

<th>{"Statut"}</th>

<th>{"Actions Admin"}</th>
</tr>
</thead>

<tbody id={"system-users-tbody"}>

</tbody>
</table>
</div>
</div>
</section>);
});
