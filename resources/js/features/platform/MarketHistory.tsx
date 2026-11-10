import { router } from '@inertiajs/react';
import { useEffect, useId, useState, type PointerEvent } from 'react';
import { Panel } from './ui';
import './market-history.css';

export type MarketHistoryData = {
    start: number;
    end: number;
    points: { at: number; yes: number }[];
};

const timestamp = new Intl.DateTimeFormat('fr', { dateStyle: 'medium', timeStyle: 'short' });
const percent = new Intl.NumberFormat('fr', { maximumFractionDigits: 2 });
const ticks = [100, 75, 50, 25, 0];

export default function MarketHistory({ history, open, status }: { history: MarketHistoryData; open: boolean; status: string }) {
    const [selected, setSelected] = useState<number | null>(null);
    const revealId = useId();
    const { start, end, points } = history;
    const latest = points.length - 1;
    const index = selected === null ? latest : Math.min(selected, latest);
    const point = points[index];
    const dateLabel = (at: number) => timestamp.format(at * 1000);
    const x = (at: number) => (at - start) / Math.max(end - start, 1) * 100;

    useEffect(() => {
        if (!open) return;
        const interval = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['market', 'marketHistory'] });
            }
        }, 20000);
        return () => window.clearInterval(interval);
    }, [open]);

    const line = (yes: boolean) => {
        const y = (share: number) => yes ? 100 - share : share;
        return points.map(({ at, yes: share }, i) => i === 0
            ? `M ${x(at)} ${y(share)}` : `H ${x(at)} V ${y(share)}`).join(' ') + ` H ${x(end)}`;
    };

    function inspect(event: PointerEvent<SVGSVGElement>) {
        if (event.type === 'pointermove' && event.pointerType === 'touch') return;
        const bounds = event.currentTarget.getBoundingClientRect();
        const at = start + (event.clientX - bounds.left) / bounds.width * (end - start);
        let current = 0;
        while (current < latest && points[current + 1].at <= at) current++;
        setSelected(current);
    }

    return <Panel title="Évolution du marché" className="p-market-history">
        <p className="p-market-history-caption">Part du total des mises placées sur le Oui et le Non.</p>
        {!point ? <div className="p-market-history-empty">
            <strong>Aucune mise enregistrée</strong>
            <p>Le graphique commence à la première prise de position.</p>
        </div> : <>
            <div className="p-market-history-summary">
                <div className="p-market-history-values">
                    <span className="p-market-history-yes"><i aria-hidden="true"/> Oui <strong>{percent.format(point.yes)} %</strong></span>
                    <span className="p-market-history-no"><i aria-hidden="true"/> Non <strong>{percent.format(100 - point.yes)} %</strong></span>
                </div>
                <time dateTime={new Date(point.at * 1000).toISOString()}>{dateLabel(point.at)}</time>
            </div>
            <div className="p-market-history-chart">
                <div className="p-market-history-axis" aria-hidden="true">
                    {ticks.map(tick => <span key={tick} style={{ top: `${100 - tick}%` }}>{tick} %</span>)}
                </div>
                <div className="p-market-history-plot">
                    <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"
                        onPointerMove={inspect} onPointerDown={inspect}
                        onPointerLeave={event => { if (event.pointerType !== 'touch') setSelected(null); }}>
                        {ticks.map(tick => <path key={tick} className="p-market-history-grid" d={`M 0 ${100 - tick} H 100`}/>)}
                        <defs><clipPath id={revealId}><rect className="p-market-history-reveal" x="-2" y="-2" width="104" height="104"/></clipPath></defs>
                        <g clipPath={`url(#${revealId})`}>
                            <path className="p-market-history-line p-market-history-yes" d={line(true)}/>
                            <path className="p-market-history-line p-market-history-no" d={line(false)}/>
                        </g>
                    </svg>
                    <div className="p-market-history-cursor" style={{ left: `${x(selected === null ? end : point.at)}%` }} aria-hidden="true">
                        <i className="p-market-history-yes" style={{ top: `${100 - point.yes}%` }}/>
                        <i className="p-market-history-no" style={{ top: `${point.yes}%` }}/>
                    </div>
                </div>
            </div>
            {latest > 0 && <input className="p-market-history-scrubber" type="range" min={0} max={latest} value={index}
                aria-label="Parcourir l’historique du marché"
                aria-valuetext={`${dateLabel(point.at)}, Oui ${percent.format(point.yes)} %, Non ${percent.format(100 - point.yes)} %`}
                onChange={event => setSelected(Number(event.target.value))}/>}
        </>}
        <div className="p-market-history-dates">
            <span><strong>Création</strong>{dateLabel(start)}</span>
            <span><strong>{open || status === 'pending' || status === 'rejected' ? 'Dernière mise à jour' : 'Clôture'}</strong>{dateLabel(end)}</span>
        </div>
    </Panel>;
}
