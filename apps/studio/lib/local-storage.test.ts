import { clearLocalStorage, LOCAL_STORAGE_KEYS } from 'common'
import { describe, expect, it } from 'vitest'

describe('clearLocalStorage', () => {
  it('preserves queue operations preferences while removing non-allowlisted keys', () => {
    window.localStorage.clear()

    window.localStorage.setItem(LOCAL_STORAGE_KEYS.UI_PREVIEW_QUEUE_OPERATIONS, 'true')
    window.localStorage.setItem('not-allowlisted', 'remove-me')

    clearLocalStorage()

    expect(window.localStorage.getItem(LOCAL_STORAGE_KEYS.UI_PREVIEW_QUEUE_OPERATIONS)).toBe('true')
    expect(window.localStorage.getItem('not-allowlisted')).toBeNull()
  })
})
