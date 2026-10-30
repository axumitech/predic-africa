import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { usePage } from '@inertiajs/react';

export type Minor = number | string;
export type Fx = { base: string; currency: string; scale: number; available: boolean; rate: string | null; as_of: string | null; expires_at: string | null; provider: string; server_time: string; min_stake: string | null; max_stake: string | null };
const scales: Record<string, number> = { XAF: 0, XOF: 0, CDF: 2, KES: 2, RWF: 0, UGX: 0, ZMW: 2, SLE: 2 };
export const currencyScale = (currency: string) => scales[currency] ?? 0;
export const formatRate = (rate: string) => rate.includes('.') ? rate.replace(/0+$/, '').replace(/\.$/, '') : rate;
export function formatMinor(value: Minor, currency: string): string {
    const integer = BigInt(String(value));
    const scale = currencyScale(currency), factor = 10n ** BigInt(scale), absolute = integer < 0n ? -integer : integer;
    const whole = new Intl.NumberFormat('fr-FR').format(absolute / factor);
    return `${integer < 0n ? '−' : ''}${whole}${scale ? ',' + String(absolute % factor).padStart(scale, '0') : ''}`;
}
export function inputMinor(value: Minor, currency: string): string {
    const scale = currencyScale(currency), factor = 10n ** BigInt(scale), integer = BigInt(String(value));
    return `${integer / factor}${scale ? '.' + String(integer % factor).padStart(scale, '0') : ''}`;
}
export function formatDecimal(value: string, currency: string): string {
    const [whole, fraction = ''] = value.split('.');
    return formatMinor(BigInt(whole + fraction.padEnd(currencyScale(currency), '0')).toString(), currency);
}
export function convertReference(value: Minor, fx: Fx): string | null {
    if (!fx.rate || !/^\d+(?:\.\d+)?$/.test(fx.rate)) return null;
    const [whole, fraction = ''] = fx.rate.split('.');
    return String(BigInt(String(value)) * BigInt(whole + fraction) * (10n ** BigInt(fx.scale)) / (10n ** BigInt(fraction.length)));
}
type CurrencyState = { fx: Fx | undefined; fresh: boolean; refresh: () => Promise<void> };
const CurrencyContext = createContext<CurrencyState | null>(null);
export function CurrencyProvider({ initial, enabled, children }: { initial?: Fx; enabled: boolean; children: ReactNode }) {
    const [fx, setFx] = useState(initial);
    const [clock, setClock] = useState(Date.now());
    const [offset, setOffset] = useState(initial ? Date.parse(initial.server_time) - Date.now() : 0);
    useEffect(() => { setFx(initial); setOffset(initial ? Date.parse(initial.server_time) - Date.now() : 0); }, [initial]);
    const refresh = async () => {
        const response = await fetch('/fx/rates', { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (response.ok) { const value: Fx = await response.json(); setOffset(Date.parse(value.server_time) - Date.now()); setFx(value); }
    };
    useEffect(() => {
        if (!enabled || initial?.currency === 'XAF') return;
        let stopped = false;
        const timer = window.setInterval(() => {
            if (!stopped && document.visibilityState === 'visible') void refresh().catch(() => {});
        }, 60000);
        const tick = window.setInterval(() => setClock(Date.now()), 1000);
        return () => { stopped = true; window.clearInterval(timer); window.clearInterval(tick); };
    }, [enabled, initial?.currency]);
    const fresh = !!fx?.available && (fx.currency === 'XAF' || (!!fx.expires_at && Date.parse(fx.expires_at) > clock + offset));
    return <CurrencyContext.Provider value={{ fx, fresh, refresh }}>{children}</CurrencyContext.Provider>;
}
export function useCurrency(): CurrencyState {
    const context = useContext(CurrencyContext);
    const props = usePage<{fx?: Fx}>().props;
    return context || { fx: props.fx, fresh: !!props.fx?.available, refresh: async () => {} };
}
export function ReferenceAmount({ value, baseOnly = false }: { value: Minor; baseOnly?: boolean }) {
    const { fx, fresh } = useCurrency();
    if (baseOnly || !fx || fx.currency === 'XAF') return <>{formatMinor(value, 'XAF')} XAF</>;
    const converted = fresh ? convertReference(value, fx) : null;
    if (converted === null) return <span title="Le taux de conversion est indisponible.">— {fx.currency} <small>· conversion indisponible</small></span>;
    return <span title={`Équivalent indicatif de ${formatMinor(value, 'XAF')} XAF · taux du ${new Date(fx.as_of!).toLocaleString('fr-FR')}`}>≈ {formatMinor(converted, fx.currency)} {fx.currency}</span>;
}
export function FxNotice() {
    const {fx, fresh, refresh} = useCurrency();
    const [updating, setUpdating] = useState(false);
    if (!fx || fx.currency === 'XAF') return null;
    return <div className={`p-fx-notice ${fresh ? '' : 'p-fx-notice--stale'}`} role="status"><div><strong>Votre portefeuille · {fx.currency}</strong>{fresh
        ? <p>1 XAF ≈ {formatRate(fx.rate!)} {fx.currency} · Taux quotidien du {new Date(fx.as_of!).toLocaleString('fr-FR')}. Les équivalents des marchés sont indicatifs.</p>
        : <p>Le taux de change est indisponible ou trop ancien. Les nouvelles participations attendent son actualisation. Vos fonds et vos positions restent conservés.</p>}</div><button type="button" disabled={updating} onClick={() => { setUpdating(true); void refresh().catch(() => {}).finally(() => setUpdating(false)); }}>{updating ? 'Actualisation…' : 'Actualiser l’affichage'}</button></div>;
}
