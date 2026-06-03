const cache = {}
const pending = {}

export function useCache() {
  async function fetch(key, fetcher) {
    if (cache[key] !== undefined) return cache[key]
    return forceFetch(key, fetcher)
  }

  async function refresh(key, fetcher) {
    delete cache[key]
    return forceFetch(key, fetcher)
  }

  async function forceFetch(key, fetcher) {
    if (pending[key]) return pending[key]
    pending[key] = fetcher().then(data => {
      cache[key] = data
      delete pending[key]
      return data
    }).catch(err => {
      delete pending[key]
      throw err
    })
    return pending[key]
  }

  return { fetch, refresh }
}
