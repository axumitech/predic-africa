import { ActionForm, categories, date, type Market } from './ui';

export default function MarketReviewForm({ market, revision }: { market: Market; revision: string }) {
    const action = `/admin/markets/${market.id}/moderate`;
    const officialDraft = market.pipeline_source?.startsWith('africa:');
    return <>
        <ActionForm action={action} initial={{
            revision, status: 'open', title: market.title, description: market.description,
            category: market.category, closes_at: market.closes_at.replace(' ', 'T').slice(0, 16),
            yes_odds: String(market.yes_odds), no_odds: String(market.no_odds),
        }} fields={[
            { name: 'title', label: 'Question du marché', minLength: 10, maxLength: 200 },
            { name: 'description', label: 'Règles et sources', type: 'textarea', minLength: 20, maxLength: 5000 },
            { name: 'category', label: 'Catégorie', options: categories.map(value => ({ value, label: value })) },
            { name: 'closes_at', label: 'Clôture (UTC)', type: 'datetime-local', hint: market.source_closes_at
                ? `Clôture maximale selon la source officielle dans votre fuseau horaire · ${date(market.source_closes_at)}.` : undefined },
            { name: 'yes_odds', label: 'Cote Oui en centièmes', type: 'number', min: 101, max: 1000 },
            { name: 'no_odds', label: 'Cote Non en centièmes', type: 'number', min: 101, max: 1000 },
            { name: 'status', label: 'Décision', options: [
                { value: 'open', label: 'Publier ce marché' },
                { value: 'pending', label: 'Garder en brouillon' },
            ] },
        ]} submit="Vérifier la décision">
            {officialDraft && <p>Vérifiez le calendrier officiel et fixez les cotes avant publication. Les cotes initiales du brouillon sont indicatives.</p>}
        </ActionForm>
        <ActionForm action={action} initial={{ status: 'rejected', revision }} fields={[]} submit="Rejeter le brouillon"/>
    </>;
}
