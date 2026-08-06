/**
 * Client HTTP minimal.
 *
 * Axios n'apporterait rien ici : quelques appels JSON same-origin, le cookie de
 * session et le jeton CSRF suffisent.
 *
 * Deux comportements comptent pour cette application :
 *  - un 401 renvoie à l'écran de connexion (session expirée ou compte supprimé) ;
 *  - un 409 signale qu'une plateforme a refusé l'opération. Ce n'est pas un bug
 *    à masquer : l'équipe doit savoir que rien n'a été appliqué.
 */

function csrfToken() {
  const cookie = document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN='))

  return cookie ? decodeURIComponent(cookie.split('=')[1]) : ''
}

export class RemoteRefusal extends Error {
  constructor(message, failure) {
    super(message)
    this.failure = failure
  }
}

/**
 * Erreurs de saisie, à replacer sous les champs concernés plutôt que dans une
 * annonce générale.
 */
export class ValidationFailed extends Error {
  constructor(message, errors) {
    super(message)
    this.errors = errors
  }
}

async function request(method, url, body) {
  const response = await fetch(url, {
    method,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': csrfToken(),
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  if (response.status === 401 || response.status === 419) {
    window.location.href = '/login'

    throw new Error('Session expirée.')
  }

  const payload = await response.json().catch(() => ({}))

  if (response.status === 422) {
    throw new ValidationFailed(payload.message ?? 'Vérifiez les informations saisies.', payload.errors ?? {})
  }

  if (response.status === 409) {
    throw new RemoteRefusal(payload.message ?? "La plateforme a refusé l'opération.", payload.failure)
  }

  if (!response.ok) {
    throw new Error(payload.message ?? "L'opération a échoué.")
  }

  return payload
}

export const http = {
  get: url => request('GET', url),
  post: (url, body) => request('POST', url, body ?? {}),
  put: (url, body) => request('PUT', url, body ?? {}),
  delete: url => request('DELETE', url),
}
