// assets/app.js
// Comportements de la page : les contrôles qui font foi restent côté serveur, en PHP.

(function () {
    // ---------- Formulaire de déclaration d'activité ----------
    const formulaire = document.getElementById('form-activite');
    if (formulaire) {
        // Changer de type n'affiche ou ne masque que les champs propres au type :
        // les champs communs ne sont jamais touchés et gardent donc leur saisie.
        const selectType = document.getElementById('type_activite_id');
        const blocsType = formulaire.querySelectorAll('[data-champs-type]');
        function afficherChampsDuType() {
            blocsType.forEach(function (bloc) {
                bloc.hidden = bloc.dataset.champsType !== selectType.value;
            });
        }
        selectType.addEventListener('change', afficherChampsDuType);
        afficherChampsDuType();

        // Onglets : "Co-participants" n'est accessible que pour une activité collective.
        const ongletInfos = document.getElementById('onglet-infos');
        const ongletCo = document.getElementById('onglet-co');
        const panneauInfos = document.getElementById('panneau-infos');
        const panneauCo = document.getElementById('panneau-co');
        function ouvrirOnglet(nom) {
            const co = nom === 'co';
            panneauInfos.hidden = co;
            panneauCo.hidden = !co;
            ongletInfos.setAttribute('aria-selected', String(!co));
            ongletCo.setAttribute('aria-selected', String(co));
        }
        ongletInfos.addEventListener('click', function () { ouvrirOnglet('infos'); });
        ongletCo.addEventListener('click', function () { ouvrirOnglet('co'); });

        // Lignes de co-participants.
        const liste = document.getElementById('liste-participants');
        const boutonAjouter = document.getElementById('ajouter-participant');
        const modele = document.getElementById('modele-participant');
        let prochain = Number(boutonAjouter.dataset.prochain);
        function ajouterLigne() {
            const html = modele.innerHTML.replaceAll('__i__', String(prochain));
            prochain += 1;
            liste.insertAdjacentHTML('beforeend', html);
        }
        boutonAjouter.addEventListener('click', ajouterLigne);
        liste.addEventListener('click', function (evenement) {
            if (evenement.target.classList.contains('retirer-ligne')) {
                evenement.target.closest('.ligne-participant').remove();
            }
        });

        // Passer de "individuelle" à "collective" active l'onglet des co-participants.
        formulaire.querySelectorAll('input[name="mode"]').forEach(function (bouton) {
            bouton.addEventListener('change', function () {
                const collective = bouton.value === 'collective' && bouton.checked;
                ongletCo.disabled = !collective;
                if (collective) {
                    if (liste.children.length === 0) {
                        ajouterLigne();
                    }
                    ouvrirOnglet('co');
                } else {
                    ouvrirOnglet('infos');
                }
            });
        });

        ouvrirOnglet(formulaire.dataset.onglet);
    }

    // ---------- Sélection multiple dans le dossier ----------
    const toutSelectionner = document.getElementById('tout-selectionner');
    if (toutSelectionner) {
        const cases = document.querySelectorAll('.case-activite');
        toutSelectionner.addEventListener('change', function () {
            cases.forEach(function (caseActivite) {
                caseActivite.checked = toutSelectionner.checked;
            });
        });
    }
})();
