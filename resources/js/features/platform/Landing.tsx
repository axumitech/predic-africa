import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { LanguageSelect, useLocale } from '../../content/locale';
import { faq, product } from '../../content/product';
import { Drawer } from '../../components/ui';
import { useEffect } from 'react';
import { money, type Market, type Pagination } from './ui';
import '../../../css/app.css';
import '../../../css/official-landing.css';

export default function Landing({ authenticated, markets, stats = {} }: { authenticated: boolean; markets?: Pagination<Market>; stats?: Record<string, number> }) {
    const { locale } = useLocale();
    const fr = locale === 'fr';
    const text = (a: string, b: string) => fr ? a : b;
    const access = authenticated ? '/dashboard' : '/login';
    const [menu, setMenu] = useState(false);
    useEffect(() => {
        const desktop = window.matchMedia('(min-width: 1024px)');
        const close = () => { if (desktop.matches) setMenu(false); };
        desktop.addEventListener('change', close);
        return () => desktop.removeEventListener('change', close);
    }, []);
    const [stake, setStake] = useState('1000');
    const [selected, setSelected] = useState('');
    const market = markets?.data.find(m => String(m.id) === selected) || markets?.data[0];
    const amount = /^\d+$/.test(stake) ? Math.min(1000000, Number(stake)) : 0;
    const payout = market ? Math.floor(amount * market.yes_odds / 100) : 0;
    const facts = product.facts[locale];
    const preview = markets?.data.slice(0, 2) || [];
    return <div id="official-landing" className="official-landing">
        <Head title={text('La Bourse de Prédictions en Afrique', 'Prediction markets in Africa')} />
        <a className="official-skip" href="#main-content">{text('Aller au contenu', 'Skip to content')}</a>
        <header className="landing-header">
            <Link className="landing-header__brand" href="/" aria-label="PredicAfrica"><img width="1460" height="481" src="/HOR_WG.png" alt="PredicAfrica" className="brand-img" /></Link>
            <nav className="landing-nav" aria-label={text('Navigation principale', 'Main navigation')}>
                <a href="#how-it-works" className="landing-nav__link" onClick={() => setMenu(false)}>{text('Comment négocier', 'How it works')}</a>
                <a href="#landing-markets" className="landing-nav__link" onClick={() => setMenu(false)}>{text('Marchés', 'Markets')}</a>
                <a href="#faq" className="landing-nav__link" onClick={() => setMenu(false)}>FAQ</a>
                <a href="#contact" className="landing-nav__link" onClick={() => setMenu(false)}>Support</a>
            </nav>
            <LanguageSelect />
            <Link className="btn btn--primary" id="btn-enter-bourse" href={access}>{text('Accéder à la Bourse', 'Open my workspace')}</Link>
            <button className="official-menu" type="button" aria-controls="landing-mobile-navigation" aria-haspopup="dialog" aria-expanded={menu} aria-label={text('Ouvrir le menu', 'Open menu')} onClick={() => setMenu(!menu)}>☰</button>
        </header>
        <Drawer id="landing-mobile-navigation" title={text('Navigation', 'Navigation')} open={menu} onClose={() => setMenu(false)}>
            <nav className="official-mobile-links" aria-label={text('Navigation mobile', 'Mobile navigation')}>
                {[['#how-it-works', text('Comment négocier', 'How it works')], ['#landing-markets', text('Marchés', 'Markets')], ['#faq', 'FAQ'], ['#contact', 'Support']].map(([href, label]) => <a key={href} href={href} onClick={() => setMenu(false)}>{label}<span aria-hidden="true">↗</span></a>)}
                <Link href={access} onClick={() => setMenu(false)}>{text('Mon espace', 'My workspace')}<span aria-hidden="true">→</span></Link>
            </nav>
        </Drawer>
        <main id="main-content" tabIndex={-1}>
            <div className="landing-hero-wrapper"><section className="landing-hero">
                <div className="landing-hero__content"><span className="hero-badge">{text('Vos convictions prennent position', 'Put your convictions into play')}</span>
                    <h1 className="hero-title">{text('La première Bourse de Prédictions en Afrique', 'Africa’s prediction marketplace')}</h1>
                    <p className="hero-desc">{text('Politique, sport, musique et économie : explorez les questions qui font bouger le continent. Choisissez Oui ou Non et suivez vos positions à cote fixe.', 'Politics, sport, music and the economy: explore the questions shaping the continent. Choose Yes or No and follow your fixed-odds positions.')}</p>
                    <div className="hero-actions"><Link className="btn btn--primary btn--lg" id="btn-hero-start" href={authenticated ? '/markets' : '/register'}>{text('Commencer à négocier', 'Get started')} →</Link></div>
                    <div className="hero-metrics">
                        <div className="hero-metric"><span className="metric-val">{money(stats.volume || 0)} XAF</span><span className="metric-lbl">{text('Volume échangé', 'Traded volume')}</span></div>
                        <div className="hero-metric"><span className="metric-val">{stats.open_markets || 0}</span><span className="metric-lbl">{text('Marchés ouverts', 'Open markets')}</span></div>
                        <div className="hero-metric"><span className="metric-val">XAF</span><span className="metric-lbl">{text('Devise du portefeuille', 'Wallet currency')}</span></div>
                    </div>
                </div>
                <div className="landing-hero__visual">
                    {preview.length ? preview.map((m, i) => <article className={`mock-card mock-card--${i + 1}`} key={m.id}>
                        <span className="mock-tag mock-tag--politics">{m.category}</span><h3><Link href={`/markets/${m.id}`}>{m.title}</Link></h3>
                        <div className="mock-odds"><span>{text('Oui', 'Yes')} @ {(m.yes_odds / 100).toFixed(2)}</span><span>{text('Non', 'No')} @ {(m.no_odds / 100).toFixed(2)}</span></div>
                    </article>) : <><article className="mock-card mock-card--1"><span className="mock-tag mock-tag--politics">PredicAfrica</span><h3>{text('Votre prochaine conviction commence ici.', 'Your next conviction starts here.')}</h3><div className="mock-odds"><span>{text('Oui', 'Yes')}</span><span>{text('Non', 'No')}</span></div></article><article className="mock-card mock-card--2"><span className="mock-tag mock-tag--music">{text('Des règles claires', 'Clear rules')}</span><h3>{text('Une question. Deux possibilités.', 'One question. Two possibilities.')}</h3><p>{text('Les marchés publiés apparaîtront ici.', 'Published markets will appear here.')}</p></article></>}
                </div>
            </section></div>
            <section id="how-it-works" className="landing-section"><h2 className="section-title text-center">{text('Comment ça marche ?', 'How does it work?')}</h2><p className="section-desc text-center">{text('De votre première conviction au résultat, en quatre étapes.', 'From your first conviction to the outcome in four steps.')}</p><div className="steps-grid">{[
                [text('Créez votre compte', 'Create your account'), text('Retrouvez vos positions, vos opérations et vos demandes dans un espace personnel.', 'Manage your positions, transactions and support requests in your workspace.')],
                [text('Alimentez votre portefeuille', 'Fund your wallet'), text('Utilisez un opérateur Mobile Money activé. Votre solde est crédité après confirmation NDSGPay.', 'Use an enabled Mobile Money operator. Funds become available after NDSGPay confirmation.')],
                [text('Prenez position', 'Take a position'), text('Lisez les règles, choisissez Oui ou Non et vérifiez votre mise et votre cote.', 'Read the rules, choose Yes or No and check your stake and odds.')],
                [text('Suivez le résultat', 'Follow the outcome'), text('La source de résolution est enregistrée. Les gains ou remboursements rejoignent votre portefeuille.', 'The settlement source is recorded. Winnings or refunds are credited to your wallet.')],
            ].map(([title, body], i) => <article className="step-card" key={title}><div className="step-num">{i + 1}</div><h3>{title}</h3><p>{body}</p></article>)}</div></section>
            <section id="landing-markets" className="landing-section landing-section--alt"><h2 className="section-title text-center">{text('Tous les marchés', 'Explore the markets')}</h2><p className="section-desc text-center">{text('Des événements publiés, des règles vérifiables et des cotes connues à l’avance.', 'Published events, verifiable rules and odds you know in advance.')}</p>
                <div className="predictions-grid">{markets?.data.length ? markets.data.map(m => <article className="predict-card" key={m.id}><div className="predict-card__header"><span className="category-tag">{m.category}</span><span>{text('Ouvert', 'Open')}</span></div><h3 className="predict-card__question"><Link href={`/markets/${m.id}`}>{m.title}</Link></h3><div className="predict-card__odds"><Link className="btn btn--odds" href={`/markets/${m.id}`}>{text('Oui', 'Yes')} ×{(m.yes_odds / 100).toFixed(2)}</Link><Link className="btn btn--odds" href={`/markets/${m.id}`}>{text('Non', 'No')} ×{(m.no_odds / 100).toFixed(2)}</Link></div></article>) : <p className="official-empty">{text('Aucun marché ouvert pour le moment. Revenez découvrir les prochaines publications.', 'There are no open markets yet. Check back for the next published events.')}</p>}</div>
                <div className="text-center"><Link className="btn btn--primary btn--lg" href={authenticated ? '/markets' : '/login'}>{text('Accéder à la Bourse pour négocier', 'Open the marketplace')}</Link></div>
            </section>
            <section id="faq" className="landing-section"><h2 className="section-title text-center">{text('Foire Aux Questions', 'Frequently asked questions')}</h2><p className="section-desc text-center">{text('Tout ce qu’il faut savoir sur PredicAfrica.', 'What you need to know about PredicAfrica.')}</p><div className="faq-accordion-wrapper">{faq[locale].map(([q, a]) => <details className="faq-item" key={q}><summary className="faq-question">{q}</summary><div className="official-faq-answer"><p>{a}</p></div></details>)}</div></section>
            {market && <section id="yield-calc" className="landing-section landing-section--alt"><h2 className="section-title text-center">{text('Estimez votre retour potentiel', 'Estimate your potential return')}</h2><p className="section-desc text-center">{facts.fixed}</p><div className="creator-card official-calculator"><label>{text('Marché · position Oui', 'Market · Yes position')}<select value={selected || String(market.id)} onChange={e => setSelected(e.target.value)}>{markets?.data.map(m => <option value={m.id} key={m.id}>{m.title}</option>)}</select></label><label>{text('Mise en XAF', 'Stake in XAF')}<input type="number" min="100" max="1000000" step="1" value={stake} onChange={e => setStake(e.target.value)} /></label><output aria-live="polite">{text('Retour si gagné', 'Return if won')} : <strong>{money(payout)} XAF</strong></output><p>{text('Mise incluse. Si perdu : 0 XAF retourné. Ce calcul ne place aucune mise.', 'Includes your stake. If lost: 0 XAF returned. This calculation does not place a position.')}</p></div></section>}
            <section id="contact" className="landing-section"><h2 className="section-title text-center">Contact & Support</h2><p className="section-desc text-center">{text('Une question sur votre compte, un marché ou un paiement ?', 'A question about your account, a market or a payment?')}</p><div className="contact-layout official-contact"><div className="contact-card"><h3>{text('Centre d’aide', 'Help centre')}</h3><p>{text('Consultez les étapes et les règles de fonctionnement.', 'Read about how the service works.')}</p><Link href="/help">{text('Consulter l’aide', 'Read the help centre')} →</Link></div><div className="contact-card"><h3>{text('Votre conversation', 'Your support conversation')}</h3><p>{text('Ouvrez un ticket et suivez les réponses depuis votre compte.', 'Open a ticket and follow the replies in your account.')}</p><Link href={authenticated ? '/support' : '/login'}>{text('Contacter le support', 'Contact support')} →</Link></div></div></section>
        </main>
        <footer className="landing-footer official-footer"><p>© {new Date().getFullYear()} PredicAfrica. {text('Tous droits réservés.', 'All rights reserved.')}</p><nav><Link href="/terms">{text('Conditions générales', 'Terms')}</Link><Link href="/privacy">{text('Confidentialité', 'Privacy')}</Link><Link href="/data">{text('Gestion des données', 'Your data')}</Link></nav></footer>
    </div>;
}
