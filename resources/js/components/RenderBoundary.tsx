import { Component, type ReactNode } from 'react';
export default class RenderBoundary extends Component<{children:ReactNode},{failed:boolean}> {
 state={failed:false};
 static getDerivedStateFromError(){return {failed:true};}
 render(){if(this.state.failed)return <main className="pa-render-error"><span>PredicAfrica</span><h1>Cette page n’a pas pu s’afficher.</h1><p>Vos opérations enregistrées restent conservées. Rechargez la page pour réessayer.</p><button onClick={()=>window.location.reload()}>Recharger la page</button><a href="/">Retour à l’accueil</a></main>;return this.props.children;}
}
