import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
export default function ConnectionStatus() {
 const [pending,setPending]=useState(false);const [slow,setSlow]=useState(false);const [offline,setOffline]=useState(!navigator.onLine);
 useEffect(()=>{let timer:ReturnType<typeof setTimeout>;const start=router.on('start',()=>{setPending(true);clearTimeout(timer);timer=setTimeout(()=>setSlow(true),4000);});const finish=router.on('finish',()=>{setPending(false);setSlow(false);clearTimeout(timer);});const online=()=>setOffline(!navigator.onLine);window.addEventListener('online',online);window.addEventListener('offline',online);return()=>{start();finish();clearTimeout(timer);window.removeEventListener('online',online);window.removeEventListener('offline',online);};},[]);
 return <>{pending&&<div className="pa-loading-line" role="status" aria-label="Chargement en cours"/>}{(offline||slow)&&<div className="pa-connection" role="status">{offline?'Connexion interrompue. Vos informations saisies restent à l’écran.':'La réponse prend plus de temps que prévu. Patientez avant de recommencer une opération.'}</div>}</>;
}
