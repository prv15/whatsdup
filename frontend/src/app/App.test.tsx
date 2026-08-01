import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { DashboardPage } from '../features/dashboard/DashboardPage';
describe('dashboard', () => { it('shows launch readiness', () => { render(<MemoryRouter><DashboardPage/></MemoryRouter>); expect(screen.getByText('Launch readiness')).toBeInTheDocument(); }); });
