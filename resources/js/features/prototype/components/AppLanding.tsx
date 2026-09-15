import { memo } from 'react';


// Static React shell; the isolated demo engine owns its dynamic DOM during migration.
export default memo(function AppLanding() {
 return (<div id={"app-landing"}>


<header className={"landing-header"}>
<div className={"landing-header__brand"}>
<img src="/HOR_WG.png" alt={"Wegame Logo"} className={"brand-img"} style={{"height":"40px","width":"auto"}} />
</div>

<nav className={"landing-nav"}>
<a href={"#how-it-works"} className={"landing-nav__link"} data-i18n={"nav_how_it_works"}>{"Comment négocier"}</a>

<a href={"#faq"} className={"landing-nav__link"} data-i18n={"nav_faq"}>{"FAQ"}</a>

<a href={"#contact"} className={"landing-nav__link"} data-i18n={"nav_support"}>{"Support"}</a>
</nav>

<div className={"lang-selector"}>
<button type={"button"} className={"lang-btn active"} data-lang={"fr"}>{"FR"}</button>

<button type={"button"} className={"lang-btn"} data-lang={"en"}>{"EN"}</button>
</div>

<button className={"btn btn--primary"} id={"btn-enter-bourse"} data-i18n={"nav_enter_bourse"}>{"Accéder à la Bourse"}</button>
</header>



<div className={"landing-hero-wrapper"}>
<section className={"landing-hero"}>
<div className={"landing-hero__content"}>
<span className={"hero-badge"} data-i18n={"hero_badge"}>{"Bourse d'Opinions P2P"}</span>

<h1 className={"hero-title"} data-i18n={"hero_title"}>{"La première Bourse de Prédictions en Afrique"}</h1>

<p className={"hero-desc"} data-i18n={"hero_desc"}>{"Prenez position sur la politique, la musique, le cinéma et l'économie locale. Investissez sur vos convictions, suivez les cotes et liquidez vos gains par Orange Money, MTN MoMo, Airtel Money, Wave ou Moov."}</p>

<div className={"hero-actions"}>
<button className={"btn btn--primary btn--lg"} id={"btn-hero-start"} data-i18n={"hero_start_btn"}>{"Commencer à négocier"}</button>
</div>

<div className={"hero-metrics"}>
<div className={"hero-metric"}>
<span className={"metric-val"}>{"12M+ XOF"}</span>

<span className={"metric-lbl"} data-i18n={"hero_metric_volume"}>{"Volume Échangé"}</span>
</div>

<div className={"hero-metric"}>
<span className={"metric-val"}>{"8+ Pays"}</span>

<span className={"metric-lbl"} data-i18n={"hero_metric_countries"}>{"Régions Couvertes"}</span>
</div>

<div className={"hero-metric"}>
<span className={"metric-val"}>{"15s"}</span>

<span className={"metric-lbl"} data-i18n={"hero_metric_live"}>{"Mise à jour Live"}</span>
</div>
</div>
</div>

<div className={"landing-hero__visual"}>
<div className={"mock-card mock-card--1"}>
<span className={"mock-tag mock-tag--politics"} data-i18n={"cat_politics"}>{"Politique"}</span>

<h3 data-i18n={"mock_title_senegal"}>{"Présidentielle Sénégal : Qui succédera à Diomaye Faye ?"}</h3>

<div className={"mock-odds"}>
<span data-i18n={"mock_odds_senegal_yes"}>{"Oui @ 1.85 "}
<small style={{"fontWeight":"500","opacity":"0.9","fontSize":"0.85em","marginLeft":"4px"}}>{"(53%)"}</small></span>

<span data-i18n={"mock_odds_senegal_no"}>{"Non @ 2.10 "}
<small style={{"fontWeight":"500","opacity":"0.9","fontSize":"0.85em","marginLeft":"4px"}}>{"(47%)"}</small></span>
</div>

<div style={{"display":"flex","height":"4px","borderRadius":"4px","overflow":"hidden","marginTop":"12px","backgroundColor":"var(--border-color)"}}>
<div style={{"width":"53%","backgroundColor":"var(--accent-primary)"}}></div>

<div style={{"width":"47%","backgroundColor":"rgba(255, 255, 255, 0.2)"}}></div>
</div>
</div>

<div className={"mock-card mock-card--2"}>
<span className={"mock-tag mock-tag--music"} data-i18n={"cat_music"}>{"Musique"}</span>

<h3 data-i18n={"mock_title_burna"}>{"Grammy Award 2027 pour Burna Boy ?"}</h3>

<div className={"mock-odds"}>
<span data-i18n={"mock_odds_burna_yes"}>{"Oui @ 1.65 "}
<small style={{"fontWeight":"500","opacity":"0.9","fontSize":"0.85em","marginLeft":"4px"}}>{"(58%)"}</small></span>

<span data-i18n={"mock_odds_burna_no"}>{"Non @ 2.30 "}
<small style={{"fontWeight":"500","opacity":"0.9","fontSize":"0.85em","marginLeft":"4px"}}>{"(42%)"}</small></span>
</div>

<div style={{"display":"flex","height":"4px","borderRadius":"4px","overflow":"hidden","marginTop":"12px","backgroundColor":"var(--border-color)"}}>
<div style={{"width":"58%","backgroundColor":"var(--accent-primary)"}}></div>

<div style={{"width":"42%","backgroundColor":"rgba(255, 255, 255, 0.2)"}}></div>
</div>
</div>
</div>
</section>
</div>



<section id={"how-it-works"} className={"landing-section"}>
<h2 className={"section-title text-center"} data-i18n={"steps_title"}>{"Comment ça marche ?"}</h2>

<p className={"section-desc text-center"} data-i18n={"steps_desc"}>{"Négociez sur la bourse d'opinions en 4 étapes simples."}</p>

<div className={"steps-grid"}>
<div className={"step-card"}>
<div className={"step-num"}>{"1"}</div>

<h3 data-i18n={"step_1_title"}>{"Sélectionnez votre Région"}</h3>

<p data-i18n={"step_1_desc"}>{"Choisissez votre pays dans le sélecteur pour adapter automatiquement la devise et les réseaux mobiles disponibles."}</p>
</div>

<div className={"step-card"}>
<div className={"step-num"}>{"2"}</div>

<h3 data-i18n={"step_2_title"}>{"Créditez votre Portefeuille"}</h3>

<p data-i18n={"step_2_desc"}>{"Déposez instantanément des fonds de manière sécurisée en utilisant votre compte Orange, MTN, Wave, Airtel ou Moov."}</p>
</div>

<div className={"step-card"}>
<div className={"step-num"}>{"3"}</div>

<h3 data-i18n={"step_3_title"}>{"Prenez Position"}</h3>

<p data-i18n={"step_3_desc"}>{"Achetez des parts sur le Oui ou le Non pour les questions d'actualité. Suivez les cotes et optimisez vos rendements."}</p>
</div>

<div className={"step-card"}>
<div className={"step-num"}>{"4"}</div>

<h3 data-i18n={"step_4_title"}>{"Retirez vos liquidités"}</h3>

<p data-i18n={"step_4_desc"}>{"Une fois le marché clôturé, liquidez votre position et effectuez un retrait mobile money instantané vers votre téléphone."}</p>
</div>
</div>
</section>



<section id={"landing-markets"} className={"landing-section"} style={{"background":"var(--bg-secondary)"}}>
<h2 className={"section-title text-center"}>{"Tous les marchés"}</h2>

<p className={"section-desc text-center"}>{"Aperçu des événements en cours. Connectez-vous pour négocier."}</p>

<div id={"landing-predictions-catalog"} className={"predictions-grid"} style={{"marginTop":"var(--space-xl)"}}>

</div>

<div className={"text-center"} style={{"marginTop":"var(--space-xl)"}}>
<button className={"btn btn--primary btn--lg"} onClick={() => document.getElementById("btn-enter-bourse")?.click()}>{"Accéder à la Bourse pour négocier"}</button>
</div>
</section>



<section id={"ussd-demo"} className={"landing-section"}>
<div className={"ussd-demo-layout"} style={{"display":"grid","gridTemplateColumns":"1fr","gap":"var(--space-xl)","alignItems":"center"}}>
<div className={"ussd-demo-info"}>
<span className={"hero-badge"} style={{"alignSelf":"flex-start"}} data-i18n={"ussd_badge"}>{"Exclusivité MVP"}</span>

<h2 className={"section-title"} style={{"marginTop":"10px"}} data-i18n={"ussd_title"}>{"Négociez par USSD sans Internet"}</h2>

<p className={"section-desc"} style={{"marginLeft":"0px","textAlign":"left","maxWidth":"none"}} data-i18n-html={"ussd_desc"}>{"\n                        PredicAfrica innove en simulant un protocole USSD complet. Composez le "}
<strong className={"text--primary"}>{"*855#"}</strong>
{" sur notre téléphone interactif pour consulter votre solde, acheter des parts ou retirer vos gains directement depuis les réseaux télécoms locaux simulés.\n                    "}</p>

<div className={"ussd-instructions"} style={{"display":"flex","flexDirection":"column","gap":"var(--space-sm)","marginTop":"var(--space-md)"}}>
<div style={{"display":"flex","alignItems":"center","gap":"var(--space-sm)"}}>
<span className={"step-num"} style={{"width":"28px","height":"28px","fontSize":"0.85rem","marginTop":"0px","marginRight":"0px","marginBottom":"0px","marginLeft":"0px","margin":"0px","display":"grid","placeItems":"center"}}>{"1"}</span>

<span data-i18n-html={"ussd_step_1"}>{"Composez "}
<strong>{"*855#"}</strong>
{" et cliquez sur le bouton d'appel vert."}</span>
</div>

<div style={{"display":"flex","alignItems":"center","gap":"var(--space-sm)"}}>
<span className={"step-num"} style={{"width":"28px","height":"28px","fontSize":"0.85rem","marginTop":"0px","marginRight":"0px","marginBottom":"0px","marginLeft":"0px","margin":"0px","display":"grid","placeItems":"center"}}>{"2"}</span>

<span data-i18n={"ussd_step_2"}>{"Parcourez les menus popups GSM pour acheter des parts."}</span>
</div>

<div style={{"display":"flex","alignItems":"center","gap":"var(--space-sm)"}}>
<span className={"step-num"} style={{"width":"28px","height":"28px","fontSize":"0.85rem","marginTop":"0px","marginRight":"0px","marginBottom":"0px","marginLeft":"0px","margin":"0px","display":"grid","placeItems":"center"}}>{"3"}</span>

<span data-i18n-html={"ussd_step_3"}>{"Confirmez avec le PIN "}
<code>{"1234"}</code>
{" et observez la mise à jour de vos positions."}</span>
</div>
</div>
</div>

<div className={"ussd-demo-phone-wrapper"} style={{"display":"flex","justifyContent":"center"}}>
<div className={"mock-phone"}>
<div className={"phone-screen"}>
<div className={"phone-status-bar"}>
<span id={"ussd-phone-carrier"} data-i18n={"ussd_phone_carrier"}>{"Afrique Telecom"}</span>

<span id={"ussd-phone-time"}>{"17:50"}</span>
</div>



<div className={"phone-dialer"} id={"phone-dialer-view"}>
<div className={"dialer-display"} id={"dialer-num"}>{"*855#"}</div>

<div className={"dialer-keypad"}>
<button type={"button"} className={"key-btn"} data-key={"1"}>{"1"}</button>

<button type={"button"} className={"key-btn"} data-key={"2"}>{"2"}</button>

<button type={"button"} className={"key-btn"} data-key={"3"}>{"3"}</button>

<button type={"button"} className={"key-btn"} data-key={"4"}>{"4"}</button>

<button type={"button"} className={"key-btn"} data-key={"5"}>{"5"}</button>

<button type={"button"} className={"key-btn"} data-key={"6"}>{"6"}</button>

<button type={"button"} className={"key-btn"} data-key={"7"}>{"7"}</button>

<button type={"button"} className={"key-btn"} data-key={"8"}>{"8"}</button>

<button type={"button"} className={"key-btn"} data-key={"9"}>{"9"}</button>

<button type={"button"} className={"key-btn"} data-key={"*"}>{"*"}</button>

<button type={"button"} className={"key-btn"} data-key={"0"}>{"0"}</button>

<button type={"button"} className={"key-btn"} data-key={"#"}>{"#"}</button>
</div>

<div className={"dialer-actions"}>
<button type={"button"} className={"call-btn"} id={"btn-dialer-call"}>{"📞"}</button>

<button type={"button"} className={"clear-btn"} id={"btn-dialer-clear"}>{"⌫"}</button>
</div>
</div>



<div className={"phone-ussd-container"} id={"phone-ussd-view"} style={{"display":"none"}}>
<div className={"ussd-dialog-box"}>
<div className={"ussd-dialog-text"} id={"ussd-dialog-message"}>{"\n                                        Menu Principal PredicAfrica:"}
<br />
{"\n                                        1. Investir sur un marché"}
<br />
{"\n                                        2. Solde Portefeuille"}
<br />
{"\n                                        3. Retirer des fonds\n                                    "}</div>

<input type={"text"} className={"ussd-dialog-input"} id={"ussd-dialog-input-val"} data-i18n-placeholder={"ussd_input_placeholder"} placeholder={"Saisissez un chiffre..."} />

<div className={"ussd-dialog-actions"}>
<button type={"button"} className={"ussd-dialog-btn"} id={"btn-ussd-dialog-cancel"} data-i18n={"ussd_btn_cancel"}>{"Annuler"}</button>

<button type={"button"} className={"ussd-dialog-btn"} id={"btn-ussd-dialog-send"} data-i18n={"ussd_btn_send"}>{"Envoyer"}</button>
</div>
</div>
</div>
</div>

<div className={"phone-home-btn"} id={"phone-home-btn-trigger"}></div>
</div>
</div>
</div>
</section>



<section id={"faq"} className={"landing-section landing-section--alt"}>
<h2 className={"section-title text-center"} data-i18n={"faq_title"}>{"Foire Aux Questions (FAQ)"}</h2>

<p className={"section-desc text-center"} data-i18n={"faq_desc"}>{"Tout ce qu'il faut savoir sur PredicAfrica."}</p>

<div className={"faq-accordion-wrapper"}>


<div className={"faq-column"}>
<h3 className={"faq-col-title"} data-i18n={"faq_block_1"}>{"Général & Plateforme"}</h3>

<div className={"faq-item"}>
<button className={"faq-question"} data-i18n={"faq_q1"}>{"Qu'est-ce qu'une bourse de prédictions ?"}</button>

<div className={"faq-answer"}>
<p data-i18n={"faq_a1"}>{"Une bourse de prédictions est une plateforme financière sur laquelle vous pouvez acheter des contrats (parts) liés à la réalisation d'événements futurs. Si l'événement se réalise, le contrat prend de la valeur et vous encaissez un rendement basé sur le multiplicateur de cotes garanti lors de votre achat."}</p>
</div>
</div>

<div className={"faq-item"}>
<button className={"faq-question"} data-i18n={"faq_q2"}>{"Comment fonctionne la simulation de Mobile Money ?"}</button>

<div className={"faq-answer"}>
<p data-i18n={"faq_a2"}>{"Il s'agit d'un simulateur GSM interactif. Vous pouvez simuler des recharges et des retraits d'argent en entrant un numéro et en saisissant un code secret fictif à 4 chiffres (comme 1234). Cela vous permet de valider le comportement fonctionnel de la plateforme."}</p>
</div>
</div>

<div className={"faq-item"}>
<button className={"faq-question"} data-i18n={"faq_q3"}>{"Les retraits de fonds sont-ils instantanés ?"}</button>

<div className={"faq-answer"}>
<p data-i18n={"faq_a3"}>{"Oui. En cliquant sur le bouton Retirer de votre portefeuille, le simulateur traite la transaction en 2 secondes et met à jour instantanément votre compte virtuel, comme un retrait Mobile Money en production."}</p>
</div>
</div>

<div className={"faq-item"}>
<button className={"faq-question"} data-i18n={"faq_q4"}>{"Est-ce légal et sécurisé ?"}</button>

<div className={"faq-answer"}>
<p data-i18n={"faq_a4"}>{"Il s'agit ici d'une simulation MVP destinée à présenter le design technique et l'expérience utilisateur. Aucune transaction financière réelle n'est effectuée. Les soldes et les dépôts sont purement virtuels et stockés localement."}</p>
</div>
</div>

<div className={"faq-item"}>
<button className={"faq-question"} data-i18n={"faq_q5"}>{"Quels sont les frais de transaction ?"}</button>

<div className={"faq-answer"}>
<p data-i18n={"faq_a5"}>{"La création de compte et les dépôts sont 100% gratuits. Nous prélevons une commission minime de 2% uniquement sur les gains nets réalisés lors du dénouement d'un marché."}</p>
</div>
</div>
</div>



<div className={"faq-column"}>
<h3 className={"faq-col-title"} data-i18n={"faq_block_2"}>{"Transactions & Sécurité"}</h3>

<div className={"faq-item"}>
<button className={"faq-question"} data-i18n={"faq_q6"}>{"Qui crée et résout les marchés ?"}</button>

<div className={"faq-answer"}>
<p data-i18n={"faq_a6"}>{"Les marchés sont créés par notre équipe d'experts et nos partenaires certifiés. La résolution est basée sur des sources publiques officielles et vérifiables (résultats électoraux, données officielles) pour garantir une totale transparence."}</p>
</div>
</div>

<div className={"faq-item"}>
<button className={"faq-question"} data-i18n={"faq_q7"}>{"Puis-je revendre mes parts avant la fin d'un événement ?"}</button>

<div className={"faq-answer"}>
<p data-i18n={"faq_a7"}>{"Oui. Vous avez la possibilité de liquider vos positions sur le marché secondaire avant la clôture, selon l'évolution des cotes en temps réel, afin de sécuriser vos profits ou limiter vos pertes."}</p>
</div>
</div>

<div className={"faq-item"}>
<button className={"faq-question"} data-i18n={"faq_q8"}>{"Dans quels pays le service est-il disponible ?"}</button>

<div className={"faq-answer"}>
<p data-i18n={"faq_a8"}>{"PredicAfrica est actuellement disponible dans plusieurs pays d'Afrique, avec le support des principaux opérateurs Mobile Money tels que Orange, MTN, Moov, Wave, Airtel et M-Pesa."}</p>
</div>
</div>

<div className={"faq-item"}>
<button className={"faq-question"} data-i18n={"faq_q9"}>{"Y a-t-il un montant minimum pour commencer ?"}</button>

<div className={"faq-answer"}>
<p data-i18n={"faq_a9"}>{"L'accessibilité est notre priorité. Le montant minimum de dépôt et de prise de position est de seulement 500 FCFA (ou équivalent local), vous permettant de démarrer avec un très petit budget."}</p>
</div>
</div>

<div className={"faq-item"}>
<button className={"faq-question"} data-i18n={"faq_q10"}>{"Mes données personnelles sont-elles protégées ?"}</button>

<div className={"faq-answer"}>
<p data-i18n={"faq_a10"}>{"Absolument. Nous appliquons les standards de sécurité bancaire les plus stricts. Vos données sont chiffrées de bout en bout et nous ne partageons jamais vos informations avec des tiers."}</p>
</div>
</div>
</div>
</div>
</section>



<section id={"yield-calc"} className={"landing-section landing-section--alt"}>
<h2 className={"section-title text-center"} data-i18n={"calc_title"}>{"Simulateur de Gains & Rendements"}</h2>

<p className={"section-desc text-center"} data-i18n={"calc_desc"}>{"Estimez vos profits potentiels en fonction de vos investissements et des cotes de marché."}</p>

<div className={"creator-card"} style={{"maxWidth":"600px","marginTop":"0px","marginRight":"auto","marginBottom":"0px","marginLeft":"auto","margin":"0px auto"}}>
<form id={"profit-calc-form"} className={"creator-form"}>
<div className={"form-row"}>
<div className={"form-group"}>
<label htmlFor={"calc-market"} data-i18n={"calc_label_market"}>{"Sélectionnez un Marché"}</label>

<select id={"calc-market"} style={{"backgroundColor":"var(--bg-tertiary)","border":"1px solid var(--border-color)","padding":"var(--space-sm) var(--space-md)","borderRadius":"var(--radius-md)","width":"100%"}}>
<option value={"1.85"} data-i18n={"calc_opt_1"}>{"Présidentielle Sénégal (Oui @ 1.85)"}</option>

<option value={"2.30"} data-i18n={"calc_opt_2"}>{"Grammy Award Burna Boy (Non @ 2.30)"}</option>

<option value={"2.80"} data-i18n={"calc_opt_3"}>{"Monnaie ECO CEDEAO (Oui @ 2.80)"}</option>

<option value={"1.60"} data-i18n={"calc_opt_4"}>{"FESPACO Alain Gomis (Non @ 1.60)"}</option>

<option value={"custom"} data-i18n={"calc_opt_custom"}>{"Autre Cote (Personnalisée)"}</option>
</select>
</div>

<div className={"form-group"} id={"calc-custom-odds-group"} style={{"display":"none"}}>
<label htmlFor={"calc-odds"} data-i18n={"calc_label_odds"}>{"Cote / Multiplicateur"}</label>

<input type={"number"} id={"calc-odds"} defaultValue={"2.00"} step={"0.1"} min={"1.01"} max={"10.0"} style={{"backgroundColor":"var(--bg-tertiary)","border":"1px solid var(--border-color)","padding":"var(--space-sm) var(--space-md)","borderRadius":"var(--radius-md)","width":"100%"}} />
</div>
</div>

<div className={"form-row"}>
<div className={"form-group"}>
<label htmlFor={"calc-amount"} data-i18n-html={"calc_label_amount"}>{"Montant à investir ("}
<span className={"calc-currency-lbl"}>{"XOF"}</span>
{")"}</label>

<input type={"number"} id={"calc-amount"} defaultValue={"5000"} min={"200"} step={"100"} style={{"backgroundColor":"var(--bg-tertiary)","border":"1px solid var(--border-color)","padding":"var(--space-sm) var(--space-md)","borderRadius":"var(--radius-md)","width":"100%"}} />
</div>

<div className={"form-group"}>
<label htmlFor={"calc-outcome"} data-i18n={"calc_label_outcome"}>{"Résultat Simulé"}</label>

<select id={"calc-outcome"} style={{"backgroundColor":"var(--bg-tertiary)","border":"1px solid var(--border-color)","padding":"var(--space-sm) var(--space-md)","borderRadius":"var(--radius-md)","width":"100%"}}>
<option value={"win"} data-i18n={"calc_opt_win"}>{"Position gagnante (Correcte)"}</option>

<option value={"loss"} data-i18n={"calc_opt_loss"}>{"Position perdante (Incorrecte)"}</option>
</select>
</div>
</div>

<div className={"bet-payout-estimate"} style={{"marginTop":"var(--space-md)"}}>
<div className={"estimate-row"}>
<span data-i18n={"calc_result_gross_lbl"}>{"Rendement Brut Potentiel :"}</span>

<strong id={"calc-result-gross"} className={"text--primary"}>{"9 250 XOF"}</strong>
</div>

<div className={"estimate-row"} style={{"marginTop":"4px"}}>
<span data-i18n={"calc_result_net_lbl"}>{"Bénéfice Net :"}</span>

<strong id={"calc-result-net"} className={"text--gold"}>{"+4 250 XOF"}</strong>
</div>

<div className={"estimate-row font-sm text--muted"} style={{"marginTop":"8px","display":"flex","alignItems":"center","gap":"8px"}}>
<span data-i18n={"calc_result_status_lbl"}>{"Statut Estimé :"}</span>

<span id={"calc-result-status"} className={"status-txt-badge status-txt-badge--success"} style={{"paddingTop":"2px","paddingRight":"8px","paddingBottom":"2px","paddingLeft":"8px","padding":"2px 8px","fontSize":"0.75rem"}}>{"Gagné"}</span>
</div>
</div>
</form>
</div>
</section>



<section id={"contact"} className={"landing-section"}>
<h2 className={"section-title text-center"} data-i18n={"contact_title"}>{"Contact & Support"}</h2>

<p className={"section-desc text-center"} data-i18n={"contact_desc"}>{"Notre équipe de support est disponible pour vous assister. Envoyez-nous un message."}</p>

<div className={"contact-layout"} style={{"display":"grid","gridTemplateColumns":"1fr","gap":"var(--space-xl)","maxWidth":"1000px","marginTop":"0px","marginRight":"auto","marginBottom":"0px","marginLeft":"auto","margin":"0px auto"}}>


<div className={"contact-info-col"} style={{"display":"flex","flexDirection":"column","gap":"var(--space-md)"}}>
<div className={"contact-card"}>
<h3><i className={"fa-solid fa-envelope"} style={{"marginRight":"8px"}}></i>

<span data-i18n={"contact_card_email_title"}>{"E-mail"}</span></h3>

<p>{"support@predicafrika.com"}</p>
</div>

<div className={"contact-card"}>
<h3><i className={"fa-solid fa-location-dot"} style={{"marginRight":"8px"}}></i>

<span data-i18n={"contact_card_offices_title"}>{"Bureaux"}</span></h3>

<p data-i18n={"contact_card_offices_desc"}>{"Avenue Colonel Mondjiba, Kinconnect, Kinshasa"}</p>
</div>

<div className={"contact-card"}>
<h3><i className={"fa-solid fa-phone"} style={{"marginRight":"8px"}}></i>

<span data-i18n={"contact_card_phone_title"}>{"Téléphone"}</span></h3>

<p>{"+221 33 892 04 04"}</p>
</div>
</div>



<div className={"creator-card"}>
<form id={"landing-contact-form"} className={"creator-form"}>
<div className={"form-row"}>
<div className={"form-group"}>
<label htmlFor={"contact-lastname"} data-i18n={"contact_label_lastname"}>{"Nom"}</label>

<input type={"text"} id={"contact-lastname"} data-i18n-placeholder={"contact_placeholder_lastname"} placeholder={"Votre nom"} required />
</div>

<div className={"form-group"}>
<label htmlFor={"contact-firstname"} data-i18n={"contact_label_firstname"}>{"Prénom"}</label>

<input type={"text"} id={"contact-firstname"} data-i18n-placeholder={"contact_placeholder_firstname"} placeholder={"Votre prénom"} required />
</div>
</div>

<div className={"form-group"}>
<label htmlFor={"contact-email"} data-i18n={"contact_label_email"}>{"Adresse e-mail"}</label>

<input type={"email"} id={"contact-email"} data-i18n-placeholder={"contact_placeholder_email"} placeholder={"nom@exemple.com"} required />
</div>

<div className={"form-group"}>
<label htmlFor={"contact-message"} data-i18n={"contact_label_message"}>{"Votre message"}</label>

<textarea id={"contact-message"} rows={5} data-i18n-placeholder={"contact_placeholder_message"} placeholder={"Décrivez votre demande en détail..."} required style={{"backgroundColor":"var(--bg-tertiary)","border":"1px solid var(--border-color)","padding":"var(--space-sm) var(--space-md)","borderRadius":"var(--radius-md)","fontSize":"0.9rem","transition":"var(--transition-fast)","width":"100%","color":"var(--text-primary)","resize":"vertical"}} defaultValue={""} />
</div>

<button type={"submit"} className={"btn btn--primary btn--block"} data-i18n={"contact_btn_send"}>{"Envoyer le message"}</button>
</form>

<div id={"contact-success-msg"} className={"status-txt-badge status-txt-badge--success"} data-i18n={"contact_success_msg"} style={{"display":"none","width":"100%","textAlign":"center","justifyContent":"center","padding":"var(--space-md)","marginTop":"var(--space-md)","fontSize":"0.9rem"}}>{"\n                        ✓ Message envoyé avec succès ! Notre équipe vous répondra sous 24h.\n                    "}</div>
</div>
</div>
</section>



<div className={"whatsapp-widget"} id={"whatsapp-toggle"}>
<svg viewBox={"0 0 24 24"} fill={"currentColor"} xmlns={"http://www.w3.org/2000/svg"}>
<path d={"M12.012 2c-5.506 0-9.989 4.478-9.99 9.984a9.96 9.96 0 0 0 1.333 4.982L2 22l5.233-1.371a9.936 9.936 0 0 0 4.779 1.218h.004c5.505 0 9.988-4.478 9.989-9.984 0-2.669-1.038-5.176-2.925-7.064A9.923 9.923 0 0 0 12.012 2zm5.727 14.073c-.314.444-1.543 1.208-2.115 1.264-.537.052-1.054.212-3.167-.624a10.84 10.84 0 0 1-4.394-3.87c-.636-.874-1.096-1.89-1.096-2.95 0-1.127.587-1.685.8-1.9.176-.177.447-.26.697-.26h.227c.18 0 .42-.08.647.444.25.577.85 2.083.923 2.23.074.148.125.321.023.518-.1.198-.152.322-.303.495l-.46.543c-.153.173-.314.362-.132.673.18.31.8 1.317 1.72 2.137.92.82 1.693 1.074 2.023 1.238.33.165.52.14.717-.082.197-.222.85-1.037 1.077-1.393.226-.356.452-.3.76-.185.31.115 1.968.966 2.308 1.135.34.17.568.25.65.39.083.14.083.82-.231 1.264z"}></path>
</svg>

<span className={"whatsapp-badge"}>{"1"}</span>
</div>



<div className={"whatsapp-chat"} id={"whatsapp-chat-window"}>
<div className={"whatsapp-chat__header"}>
<div className={"whatsapp-chat__avatar"}>{"PA"}</div>

<div className={"whatsapp-chat__info"}>
<span className={"chat-name"} data-i18n={"wa_chat_name"}>{"Support PredicAfrica"}</span>

<span className={"chat-status"} data-i18n={"wa_chat_status"}>{"En ligne"}</span>
</div>

<button className={"chat-close"} id={"whatsapp-chat-close"}>{"×"}</button>
</div>

<div className={"whatsapp-chat__messages"} id={"whatsapp-messages-container"}>
<div className={"chat-msg chat-msg--received"} data-i18n-html={"wa_welcome"}>{"\n                    Bonjour ! Je suis Ablaye du support PredicAfrica. Comment puis-je vous aider aujourd'hui ? 🌍\n                    "}
<span className={"msg-time"}>{"17:00"}</span>
</div>
</div>

<div className={"whatsapp-chat__input-area"}>
<input type={"text"} id={"whatsapp-user-input"} data-i18n-placeholder={"wa_input_placeholder"} placeholder={"Écrivez votre message..."} />

<button id={"whatsapp-send-btn"}>
<svg viewBox={"0 0 24 24"} fill={"none"} xmlns={"http://www.w3.org/2000/svg"}>
<path d={"M22 2L11 13M22 2L15 22L11 13M11 13L2 9L22 2"} stroke={"currentColor"} strokeWidth={"2"} strokeLinecap={"round"} strokeLinejoin={"round"}></path>
</svg>
</button>
</div>
</div>



<footer className={"landing-footer"} style={{"padding":"var(--space-lg) var(--space-md)","textAlign":"center","borderTop":"1px solid var(--border-color)","backgroundColor":"var(--bg-secondary)","marginTop":"var(--space-xxl)"}}>
<p className={"text--muted font-sm"} data-i18n={"footer_copyright"} style={{"marginBottom":"var(--space-sm)"}}>{"© 2026 PredicAfrica. Tous droits réservés. (Simulation de démonstration MVP)"}</p>

<div style={{"display":"flex","justifyContent":"center","gap":"var(--space-md)","flexWrap":"wrap"}}>
<a href={"#cgu"} id={"link-cgu"} className={"font-sm text--muted"} data-i18n={"footer_link_cgu"} style={{"textDecoration":"underline","cursor":"pointer"}}>{"Conditions Générales"}</a>

<a href={"#privacy"} id={"link-privacy"} className={"font-sm text--muted"} data-i18n={"footer_link_privacy"} style={{"textDecoration":"underline","cursor":"pointer"}}>{"Confidentialité"}</a>

<a href={"#data"} id={"link-data"} className={"font-sm text--muted"} data-i18n={"footer_link_data"} style={{"textDecoration":"underline","cursor":"pointer"}}>{"Gestion des Données"}</a>
</div>
</footer>
</div>);
});
