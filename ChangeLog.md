# ChangeLog for ShippableOrder

## Unreleased



## 2.3
- FIX : Ticket DA024347 token not transmitted if empty querry result   - *15/01/2024* - 2.3.9  
- FIX : Fix typo *24/11/2023* 2.3.8
- FIX : Fix translation key for conflict with standard key *23/11/2023* 2.3.7
- FIX : Wrong display of the stock when option "Autoriser l'expédition même si pas assez de stock" is enabled *09/11/2023* 2.3.6
- FIX : Subtotal compatibility *13/11/2023* 2.3.5
- FIX : wrong js selector *28/02/2022* 2.3.4
- FIX : Fix tooltip (wording and translation) for theorical stock on customer order *23/01/2022* 2.3.3
- FIX : Compat PHP 8.1 V17  *17/01/2023* 2.3.2
- FIX : Missing module icon  *17/10/2022* 2.3.1
- NEW : Ajout de la class TechATM pour l'affichage de la page "A propos" *11/05/2022* 2.3.0

## 2.2

- FIX: PHP 8 - *04/08/2022* - 2.2.3
- FIX: Compatibility V16 - newToken and family - *06/06/2022* - 2.2.2
- FIX: Stock's column colour correction on order - *2021-10-06* - 2.2.1
- NEW: Colonne stock virtuel - *2021-09-21* - 2.2.0

## 2.1

- FIX: v13 — 14 compatibility: societe_id,  - *2021-09-09* - 2.1.3
- FIX : Compatibility V13 - Add token renewal - *18/05/2021* 2.1.2
- FIX : le tableau d'extrafields des lignes de commandes n'est pas recopié sur les lignes d'expéditions (dans le cas où ils ont le même nom) - *29/09/2022* - 2.1.1

## 2.0
- NEW: v3.9 — 11 compatibility
    - multiselect on status search in shippable order list
    - gestion d'un entrepôt par utilisateur (extrafield issu d'une table)
      avec calcul sur cet entrepôt uniquement si sélectionné sur la fiche
      user
    - conf pour ne pas prendre en compte les expéditions brouillon dans le
      calcul des quantités déjà expédiées
    - tooltip
    - filtre sur le statut de la commande
    - gestion par ligne
    *2016-04-25* - 2.0

## 1.0
 Initial version


