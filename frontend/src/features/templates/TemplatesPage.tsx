import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CheckCircle2, Clock, FilePlus2, Pencil, RefreshCw, Send, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { api } from '../../services/api';
import type { MessageTemplate } from '../../types/operations';
import { ConfirmPanel, InlineNotice } from '../../components/feedback/Feedback';

export function TemplatesPage() {
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<MessageTemplate | null>(null);
  const [deleting, setDeleting] = useState<MessageTemplate | null>(null);
  const [submittingId, setSubmittingId] = useState<string | null>(null);
  const [notice, setNotice] = useState<{ kind: 'success' | 'error'; title: string; message?: string } | null>(null);
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['templates'],
    queryFn: async () => (await api.get<{ data: MessageTemplate[] }>('/templates')).data.data,
  });

  const sync = useMutation({
    mutationFn: async () => (await api.post('/meta/templates/sync')).data.data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['templates'] });
      setNotice({ kind: 'success', title: 'Templates synced from Meta' });
    },
    onError: (error: Error) => setNotice({ kind: 'error', title: 'Template sync failed', message: apiError(error) }),
  });

  const submit = useMutation({
    mutationFn: async (id: string) => {
      setSubmittingId(id);
      return (await api.post(`/templates/${id}/submit`)).data.data;
    },
    onSuccess: () => {
      setSubmittingId(null);
      queryClient.invalidateQueries({ queryKey: ['templates'] });
      setNotice({
        kind: 'success',
        title: 'Template submitted to Meta',
        message: 'Your template has been submitted to Meta Cloud API and is now pending review.',
      });
    },
    onError: (error: Error) => {
      setSubmittingId(null);
      setNotice({ kind: 'error', title: 'Meta template submission failed', message: apiError(error) });
    },
  });

  const create = useMutation({
    mutationFn: async (form: HTMLFormElement) => {
      const data = new FormData(form);
      const image = data.get('headerImage');
      const headerImage = image instanceof File && image.size ? await fileToDataUrl(image) : '';
      const submitToMeta = data.get('submitToMeta') === 'on';
      return (
        await api.post('/templates', {
          name: data.get('name'),
          language: data.get('language'),
          category: data.get('category'),
          body: data.get('body'),
          headerImage,
          submitToMeta,
        })
      ).data.data;
    },
    onSuccess: (_, variables) => {
      const data = new FormData(variables);
      const submitToMeta = data.get('submitToMeta') === 'on';
      queryClient.invalidateQueries({ queryKey: ['templates'] });
      setCreating(false);
      setNotice({
        kind: 'success',
        title: submitToMeta ? 'Template submitted to Meta' : 'Template draft created',
        ...(submitToMeta ? { message: 'Template created and submitted to Meta for review.' } : {}),
      });
    },
    onError: (error: Error) => setNotice({ kind: 'error', title: 'Template could not be created', message: apiError(error) }),
  });

  const update = useMutation({
    mutationFn: async (form: HTMLFormElement) => {
      const data = new FormData(form);
      return api.put(`/templates/${editing!.id}`, { category: data.get('category'), body: data.get('body') });
    },
    onSuccess: () => {
      setEditing(null);
      queryClient.invalidateQueries({ queryKey: ['templates'] });
      setNotice({ kind: 'success', title: 'Template draft updated' });
    },
    onError: (error: Error) => setNotice({ kind: 'error', title: 'Template could not be updated', message: apiError(error) }),
  });

  const remove = useMutation({
    mutationFn: async () => api.delete(`/templates/${deleting!.id}`),
    onSuccess: () => {
      setDeleting(null);
      queryClient.invalidateQueries({ queryKey: ['templates'] });
      setNotice({ kind: 'success', title: 'Template deleted' });
    },
    onError: (error: Error) => {
      setDeleting(null);
      setNotice({ kind: 'error', title: 'Template could not be deleted', message: apiError(error) });
    },
  });

  return (
    <div className="space-y-8">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p className="text-sm font-semibold text-brand-700">Message library</p>
          <h1 className="mt-1 text-3xl font-semibold tracking-tight">Templates</h1>
          <p className="mt-2 text-muted">Build compliant WhatsApp message drafts, submit them directly to Meta, and track live approval status.</p>
        </div>
        <div className="flex flex-wrap gap-3">
          <button
            disabled={sync.isPending}
            onClick={() => sync.mutate()}
            className="flex items-center justify-center gap-2 rounded-xl border border-line bg-white px-4 py-3 text-sm font-semibold hover:bg-slate-50 disabled:opacity-50"
          >
            <RefreshCw size={18} className={sync.isPending ? 'animate-spin' : ''} />
            {sync.isPending ? 'Syncing…' : 'Sync from Meta'}
          </button>
          <button
            onClick={() => setCreating(true)}
            className="flex items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-brand-800"
          >
            <FilePlus2 size={18} /> New template
          </button>
        </div>
      </div>

      {notice && <InlineNotice {...notice} onDismiss={() => setNotice(null)} />}

      {creating && (
        <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
          <div className="flex items-start justify-between">
            <div>
              <h2 className="text-xl font-semibold">Create WhatsApp Template</h2>
              <p className="mt-1 text-sm text-muted">Use lowercase letters, numbers and underscores in the name. Variables use format {"{{1}}"}, {"{{2}}"}.</p>
            </div>
            <button aria-label="Close form" onClick={() => setCreating(false)} className="rounded-lg p-2 hover:bg-gray-100">
              <X />
            </button>
          </div>
          <form
            className="mt-5 grid gap-4 md:grid-cols-2"
            onSubmit={(event) => {
              event.preventDefault();
              create.mutate(event.currentTarget);
            }}
          >
            <Field label="Template name">
              <input className="input" name="name" placeholder="festival_offer" pattern="^[a-z][a-z0-9_]{1,100}$" title="Lowercase letters, numbers and underscores only" required />
            </Field>
            <Field label="Category">
              <select className="input" name="category" defaultValue="marketing">
                <option value="marketing">Marketing</option>
                <option value="utility">Utility</option>
                <option value="authentication">Authentication</option>
              </select>
            </Field>
            <Field label="Language">
              <select className="input" name="language" defaultValue="en_US">
                <option value="en_US">English (US)</option>
                <option value="en_IN">English (India)</option>
                <option value="hi_IN">Hindi</option>
              </select>
            </Field>
            <Field label="Image header (optional)">
              <input className="input" name="headerImage" type="file" accept="image/jpeg,image/png,image/webp" />
              <span className="mt-1 block text-xs text-muted">JPEG, PNG or WebP, maximum 5 MB.</span>
            </Field>
            <div className="md:col-span-2">
              <Field label="Message body">
                <textarea className="input min-h-28" name="body" placeholder="Hello {{1}}, our special festive offer is ready for you: {{2}}." required />
              </Field>
            </div>
            <div className="md:col-span-2 flex items-center gap-2 rounded-xl bg-slate-50 p-3 border border-line/60">
              <input
                type="checkbox"
                id="submitToMeta"
                name="submitToMeta"
                defaultChecked
                className="h-4 w-4 rounded border-gray-300 text-brand-700 focus:ring-brand-700"
              />
              <label htmlFor="submitToMeta" className="text-sm font-medium text-ink cursor-pointer">
                Submit directly to Meta for review immediately (requires connected WhatsApp account)
              </label>
            </div>
            <div className="md:col-span-2 flex gap-3">
              <button disabled={create.isPending} className="rounded-xl bg-brand-700 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-50">
                {create.isPending ? 'Saving…' : 'Save template'}
              </button>
              <button type="button" onClick={() => setCreating(false)} className="rounded-xl border border-line px-4 py-3 text-sm font-semibold hover:bg-slate-50">
                Cancel
              </button>
            </div>
            {create.isError && <p className="md:col-span-2 text-sm text-red-700">{apiError(create.error)}</p>}
          </form>
        </section>
      )}

      <section className="grid gap-4 lg:grid-cols-2">
        {query.data?.map((template) => (
          <article className="rounded-2xl border border-line bg-white p-6 shadow-card flex flex-col justify-between" key={template.id}>
            <div>
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-semibold">{template.name}</p>
                  <p className="mt-1 text-xs text-muted">
                    {template.category} · {template.language}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <Status status={template.status} />
                  {(template.status === 'draft' || template.status === 'rejected') && (
                    <button
                      disabled={submit.isPending}
                      onClick={() => submit.mutate(template.id)}
                      title="Submit this template to Meta for approval"
                      className="flex items-center gap-1.5 rounded-lg bg-brand-700 px-2.5 py-1 text-xs font-semibold text-white shadow-sm hover:bg-brand-800 disabled:opacity-50"
                    >
                      <Send size={13} className={submittingId === template.id ? 'animate-spin' : ''} />
                      {submittingId === template.id ? 'Submitting…' : 'Submit to Meta'}
                    </button>
                  )}
                  {template.status === 'draft' && (
                    <button
                      aria-label={`Edit ${template.name}`}
                      onClick={() => setEditing(template)}
                      className="rounded-lg p-1.5 text-brand-700 hover:bg-brand-50"
                    >
                      <Pencil size={16} />
                    </button>
                  )}
                  <button
                    aria-label={`Delete ${template.name}`}
                    onClick={() => setDeleting(template)}
                    className="rounded-lg p-1.5 text-red-700 hover:bg-red-50"
                  >
                    <Trash2 size={16} />
                  </button>
                </div>
              </div>

              <p className="mt-4 whitespace-pre-wrap text-sm leading-6 text-ink">{template.body}</p>

              {template.variables && template.variables.length > 0 && (
                <div className="mt-3 flex flex-wrap items-center gap-1.5">
                  <span className="text-xs font-medium text-muted">Variables:</span>
                  {template.variables.map((v) => (
                    <span key={v} className="rounded-md bg-brand-50 px-2 py-0.5 font-mono text-xs font-semibold text-brand-700">
                      {`{{${v}}}`}
                    </span>
                  ))}
                </div>
              )}
            </div>

            <div className="mt-5">
              {template.status === 'draft' && (
                <p className="rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-800">
                  Draft only. Click <strong>Submit to Meta</strong> above to submit this template to Meta for review before launching campaigns.
                </p>
              )}
              {template.status === 'pending' && (
                <p className="rounded-xl bg-sky-50 p-3 text-xs leading-5 text-sky-800 flex items-center gap-2">
                  <Clock size={16} className="shrink-0 text-sky-600" />
                  <span>Submitted to Meta. Pending approval (typically 1–15 mins). Click <strong>Sync from Meta</strong> above to refresh status.</span>
                </p>
              )}
              {template.status === 'approved' && (
                <p className="rounded-xl bg-emerald-50 p-3 text-xs leading-5 text-emerald-800 flex items-center gap-2">
                  <CheckCircle2 size={16} className="shrink-0 text-emerald-600" />
                  <span>Approved by Meta. This template is active and ready for live campaigns.</span>
                </p>
              )}
              {template.status === 'rejected' && (
                <p className="rounded-xl bg-red-50 p-3 text-xs leading-5 text-red-700">
                  <strong>Rejected by Meta:</strong> {template.rejectionReason || 'Please review template guidelines and re-submit.'}
                </p>
              )}
            </div>
          </article>
        ))}
      </section>

      {query.isLoading && <p className="text-center text-muted">Loading templates…</p>}
      {!query.isLoading && !query.data?.length && (
        <section className="rounded-2xl border border-dashed border-line bg-white p-12 text-center">
          <p className="font-semibold">No templates yet</p>
          <p className="mt-2 text-sm text-muted">Create a template and submit it directly to Meta for approval.</p>
        </section>
      )}

      {editing && (
        <div className="fixed inset-0 z-40 grid place-items-center bg-slate-950/35 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
          <form
            className="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl"
            onSubmit={(event) => {
              event.preventDefault();
              update.mutate(event.currentTarget);
            }}
          >
            <h2 className="text-xl font-semibold">Edit template draft</h2>
            <p className="mt-1 text-sm text-muted">
              {editing.name} · {editing.language}
            </p>
            <Field label="Category">
              <select className="input" name="category" defaultValue={editing.category}>
                <option value="marketing">Marketing</option>
                <option value="utility">Utility</option>
                <option value="authentication">Authentication</option>
              </select>
            </Field>
            <Field label="Message body">
              <textarea className="input min-h-32" name="body" defaultValue={editing.body} required />
            </Field>
            <div className="mt-5 flex justify-end gap-3">
              <button type="button" onClick={() => setEditing(null)} className="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">
                Cancel
              </button>
              <button disabled={update.isPending} className="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-50">
                {update.isPending ? 'Saving…' : 'Save changes'}
              </button>
            </div>
          </form>
        </div>
      )}

      {deleting && (
        <ConfirmPanel
          title="Delete template?"
          message={`“${deleting.name}” will be removed. If this template was submitted to Meta, we will also request its removal from Meta.`}
          pending={remove.isPending}
          onCancel={() => setDeleting(null)}
          onConfirm={() => remove.mutate()}
        />
      )}
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="mb-2 block text-sm font-medium">{label}</span>
      {children}
    </label>
  );
}

function Status({ status }: { status: MessageTemplate['status'] }) {
  if (status === 'approved') {
    return <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Approved</span>;
  }
  if (status === 'rejected') {
    return <span className="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Rejected</span>;
  }
  if (status === 'pending') {
    return <span className="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Pending Review</span>;
  }
  return <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Draft</span>;
}

function apiError(error: Error): string {
  const candidate = error as Error & { response?: { data?: { error?: { message?: string } } } };
  return candidate.response?.data?.error?.message ?? 'The template could not be saved.';
}

function fileToDataUrl(file: File): Promise<string> {
  if (file.size > 5 * 1024 * 1024) return Promise.reject(new Error('The image must be no larger than 5 MB.'));
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result));
    reader.onerror = () => reject(new Error('The image could not be read.'));
    reader.readAsDataURL(file);
  });
}
