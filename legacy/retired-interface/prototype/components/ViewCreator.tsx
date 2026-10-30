import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function ViewCreator() {
 return (<section id={"view-creator"} className={"view-section"}>
<div className={"section-header"}>
<div>
<h2 className={"section-title"}>{"Créateur de Marché"}</h2>

<p className={"section-desc"} style={{"textAlign":"left","marginLeft":"0px","marginRight":"0px"}}>{"Devenez créateur de marché. Définissez une question d'actualité et ouvrez le carnet de commandes."}</p>
</div>
</div>

<div className={"creator-card"}>
<form id={"create-prediction-form"} className={"creator-form"}>
<div className={"form-row"}>
<div className={"form-group"}>
<label htmlFor={"create-title"}>{"Question de Marché (Prédiction)"}</label>

<input type={"text"} id={"create-title"} placeholder={"Ex: Wizkid va-t-il remplir le Stade de France en 2027 ?"} required />
</div>

<div className={"form-group"}>
<label htmlFor={"create-category"}>{"Catégorie"}</label>

<select id={"create-category"} required>
<option value={"politics"}>{"Politique"}</option>

<option value={"economy"}>{"Économie"}</option>

<option value={"music"}>{"Musique"}</option>

<option value={"cinema"}>{"Cinéma"}</option>
</select>
</div>
</div>

<div className={"form-group"}>
<label htmlFor={"create-desc"}>{"Description & Critères de Résolution"}</label>

<textarea id={"create-desc"} rows={3} placeholder={"Décrivez comment la position sera clôturée (ex: sources d'information officielles, critères exacts...)"} required defaultValue={""} />
</div>

<div className={"form-row"}>
<div className={"form-group"}>
<label htmlFor={"create-date"}>{"Date de Clôture"}</label>

<input type={"date"} id={"create-date"} required />
</div>

<div className={"form-group"}>
<label htmlFor={"create-initial-pool"} id={"create-initial-pool-label"}>{"Liquidité de départ (FCFA)"}</label>

<input type={"number"} id={"create-initial-pool"} defaultValue={"500000"} min={"10000"} required />
</div>
</div>

<h3 className={"form-subtitle"}>{"Configuration des Options de Réponse & Cotes"}</h3>

<div className={"form-row"}>
<div className={"form-group"}>
<label htmlFor={"create-opt-yes"}>{"Option A (ex: Oui)"}</label>

<input type={"text"} id={"create-opt-yes"} defaultValue={"Oui"} required />
</div>

<div className={"form-group"}>
<label htmlFor={"create-odds-yes"}>{"Cote Initiale A (ex: 1.80)"}</label>

<input type={"number"} id={"create-odds-yes"} step={"0.05"} min={"1.01"} defaultValue={"1.85"} required />
</div>
</div>

<div className={"form-row"}>
<div className={"form-group"}>
<label htmlFor={"create-opt-no"}>{"Option B (ex: Non)"}</label>

<input type={"text"} id={"create-opt-no"} defaultValue={"Non"} required />
</div>

<div className={"form-group"}>
<label htmlFor={"create-odds-no"}>{"Cote Initiale B (ex: 2.00)"}</label>

<input type={"number"} id={"create-odds-no"} step={"0.05"} min={"1.01"} defaultValue={"1.95"} required />
</div>
</div>

<div className={"form-actions"}>
<button type={"submit"} className={"btn btn--primary"}>{"Créer le Marché"}</button>

<button type={"reset"} className={"btn btn--outline"}>{"Réinitialiser"}</button>
</div>
</form>
</div>
</section>);
});
