import '../../css/app.css';
import { Head } from '@inertiajs/react';
import { useEffect } from 'react';
import { initializePrototype } from '../features/prototype/engine';
import AppLanding from '../features/prototype/components/AppLanding';
import AppLogin from '../features/prototype/components/AppLogin';
import AppWorkspace from '../features/prototype/components/AppWorkspace';
import ModalRecharge from '../features/prototype/components/ModalRecharge';
import ModalPlaceBet from '../features/prototype/components/ModalPlaceBet';
import ModalWithdraw from '../features/prototype/components/ModalWithdraw';
import ModalCgu from '../features/prototype/components/ModalCgu';
import ModalPrivacy from '../features/prototype/components/ModalPrivacy';
import ModalData from '../features/prototype/components/ModalData';
import ModalSupportReply from '../features/prototype/components/ModalSupportReply';
import ModalNewTicket from '../features/prototype/components/ModalNewTicket';

export default function Prototype() {
 useEffect(() => initializePrototype(), []);
 return <>
 <Head title="PredicAfrica — Bourse de prédictions" />
 <div className="demo-notice" role="note">Démonstration · Soldes, paiements et prédictions simulés</div>
 <AppLanding />
<AppLogin />
<AppWorkspace />
<ModalRecharge />
<ModalPlaceBet />
<ModalWithdraw />
<ModalCgu />
<ModalPrivacy />
<ModalData />
<ModalSupportReply />
<ModalNewTicket />
 </>;
}
