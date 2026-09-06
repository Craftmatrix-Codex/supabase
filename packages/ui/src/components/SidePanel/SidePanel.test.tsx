import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import SidePanel from './SidePanel'

describe('SidePanel accessibility', () => {
  it('provides an accessible dialog name for panels without a visible title', () => {
    render(
      <SidePanel visible header="Settings" hideFooter>
        <div>Panel content</div>
      </SidePanel>
    )

    expect(screen.getByRole('dialog')).toHaveAccessibleName('Settings')
  })
})
