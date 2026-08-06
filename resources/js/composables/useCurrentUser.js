/**
 * Identité de la personne connectée, déposée dans la page par Blade.
 *
 * Sert notamment à distinguer sa propre ligne dans la liste des comptes :
 * certaines actions destinées à dépanner un collègue n'ont pas de sens
 * appliquées à soi-même, et enfermeraient l'administrateur dehors.
 */
const mount = document.getElementById('app')

export function useCurrentUser() {
  const id = Number(mount?.dataset.userId ?? 0)

  return {
    id,
    name: mount?.dataset.userName ?? '',
    email: mount?.dataset.userEmail ?? '',
    isAdmin: mount?.dataset.userAdmin === '1',
    isMe: user => user?.id === id,
  }
}
