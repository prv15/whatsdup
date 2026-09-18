import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { api } from '../../services/api';
import { TestConnectionForm } from './TestConnectionForm';

afterEach(() => { cleanup(); vi.restoreAllMocks(); });
const config = { wabaId: '1376690200553798', phoneNumberId: '1349858381536228' };

describe('Restricted test connection', () => {
  it('requires token and confirmation without persisting the token in the DOM after success', async () => {
    const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: { status: 'connected' } } });
    const onConnected = vi.fn();
    render(<TestConnectionForm config={config} onConnected={onConnected} />);
    const button = screen.getByRole('button', { name: 'Connect test account' });
    expect(button).toBeDisabled();
    const input = screen.getByLabelText('Meta test access token');
    expect(input).toHaveAttribute('type', 'password');
    fireEvent.change(input, { target: { value: 'test-token-value-for-local-test' } });
    expect(button).toBeDisabled();
    fireEvent.click(screen.getByRole('checkbox'));
    fireEvent.click(button);
    await waitFor(() => expect(onConnected).toHaveBeenCalled());
    expect(post).toHaveBeenCalledWith('/meta/connection/test', { accessToken: 'test-token-value-for-local-test', confirmReplacement: true });
    expect(input).toHaveValue('');
    expect(screen.getByRole('status')).toHaveTextContent('Sync from Meta');
  });

  it('does not report webhook failure as a successful connection', async () => {
    vi.spyOn(api, 'post').mockResolvedValue({ data: { data: { status: 'webhook_error' } } });
    render(<TestConnectionForm config={config} onConnected={vi.fn()} />);
    fireEvent.change(screen.getByLabelText('Meta test access token'), { target: { value: 'test-token-value-for-local-test' } });
    fireEvent.click(screen.getByRole('checkbox'));
    fireEvent.click(screen.getByRole('button', { name: 'Connect test account' }));
    expect(await screen.findByRole('status')).toHaveTextContent('webhook setup needs attention');
  });
});
