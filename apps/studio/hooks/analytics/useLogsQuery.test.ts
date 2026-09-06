import { describe, expect, it } from 'vitest'

import { isLogsQueryCancellation } from './useLogsQuery'

describe('isLogsQueryCancellation', () => {
  it('recognizes the cancellation object thrown by the logs client', () => {
    expect(isLogsQueryCancellation({ type: 'cancelation', msg: 'operation is manually canceled' })).toBe(true)
  })

  it('does not hide ordinary query failures', () => {
    expect(isLogsQueryCancellation(new Error('network failure'))).toBe(false)
  })
})
