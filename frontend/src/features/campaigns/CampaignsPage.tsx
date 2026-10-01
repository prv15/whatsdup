import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertCircle, Megaphone, Pencil, Plus, Send, Trash2, Users, X } from 'lucide-react';
import { useState } from 'react';
import { api } from '../../services/api';
import type { Campaign, CampaignRecipient, CampaignVariableMapping, ContactGroup, ContactsResponse, MessageTemplate } from '../../types/operations';
import { ConfirmPanel, InlineNotice } from '../../components/feedback/Feedback';

export function CampaignsPage() {
  const [creating, setCreating] = useState(false);
  const [audienceType, setAudienceType] = useState<'all_opted_in' | 'selected' | 'groups'>('all_opted_in');
  const [selectedTemplateId, setSelectedTemplateId] = useState<string>('');
  const [variableConfig, setVariableConfig] = useState<Record<string, CampaignVariableMapping>>({});
  const [editing, setEditing] = useState<Campaign | null>(null);
  const [deleting, setDeleting] = useState<Campaign | null>(null);
  const [launchingCampaign, setLaunchingCampaign] = useState<Campaign | null>(null);
  const [inspectingCampaign, setInspectingCampaign] = useState<Campaign | null>(null);
  const [notice, setNotice] = useState<{ kind: 'success' | 'error'; title: string; message?: string } | null>(null);

  const queryClient = useQueryClient();
  const campaigns = useQuery({ queryKey: ['campaigns'], queryFn: async () => (await api.get<{ data: Campaign[] }>('/campaigns')).data.data });
  const templates = useQuery({ queryKey: ['templates'], queryFn: async () => (await api.get<{ data: MessageTemplate[] }>('/templates')).data.data });
  const contacts = useQuery({ queryKey: ['contacts'], queryFn: async () => (await api.get<{ data: ContactsResponse }>('/contacts')).data.data });
  const groups = useQuery({ queryKey: ['contact-groups'], queryFn: async () => (await api.get<{ data: ContactGroup[] }>('/contact-groups')).data.data });

  const recipientsQuery = useQuery({
    queryKey: ['campaign-recipients', inspectingCampaign?.id],
    queryFn: async () => (await api.get<{ data: CampaignRecipient[] }>(`/campaigns/${inspectingCampaign!.id}/recipients`)).data.data,
    enabled: !!inspectingCampaign,
  });

  const approvedTemplates = templates.data?.filter((template) => template.status === 'approved') ?? [];
  const optedIn = contacts.data?.contacts.filter((contact) => contact.consentStatus === 'opted_in') ?? [];
  const selectedTemplate = approvedTemplates.find((t) => t.id === selectedTemplateId);

  const handleTemplateSelect = (templateId: string) => {
    setSelectedTemplateId(templateId);
    const tmpl = approvedTemplates.find((t) => t.id === templateId);
    if (tmpl?.variables && tmpl.variables.length > 0) {
      const initial: Record<string, CampaignVariableMapping> = {};
      tmpl.variables.forEach((token, idx) => {
        if (idx === 0) {
          initial[token] = { type: 'contact_field', value: 'name', fallback: 'Customer' };
        } else {
          initial[token] = { type: 'static', value: '' };
        }
      });
      setVariableConfig(initial);
    } else {
      setVariableConfig({});
    }
  };

  const create = useMutation({
    mutationFn: async (form: HTMLFormElement) => {
      const data = new FormData(form);
      const audience = String(data.get('audienceType'));
      const contactIds = audience === 'selected' ? data.getAll('contactIds').map(String) : [];
      const groupIds = audience === 'groups' ? data.getAll('groupIds').map(String) : [];
      const variableMappings = Object.keys(variableConfig).length > 0 ? variableConfig : null;
      return (
        await api.post('/campaigns', {
          name: data.get('name'),
          templateId: data.get('templateId'),
          audienceType: audience,
          contactIds,
          groupIds,
          scheduledAt: data.get('scheduledAt') || null,
          variableMappings,
        })
      ).data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['campaigns'] });
      setCreating(false);
      setSelectedTemplateId('');
      setVariableConfig({});
      setNotice({ kind: 'success', title: 'Campaign draft created' });
    },
    onError: (error: Error) => setNotice({ kind: 'error', title: 'Campaign could not be created', message: apiError(error) }),
  });

  const launch = useMutation({
    mutationFn: async (id: string) => (await api.post(`/campaigns/${id}/launch`)).data.data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['campaigns'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      setNotice({ kind: 'success', title: 'Campaign queued successfully for dispatch' });
    },
    onError: (error: Error) => setNotice({ kind: 'error', title: 'Campaign could not be launched', message: apiError(error) }),
  });

  const update = useMutation({
    mutationFn: async (form: HTMLFormElement) => {
      const data = new FormData(form);
      return api.patch(`/campaigns/${editing!.id}`, { name: data.get('name'), scheduledAt: data.get('scheduledAt') || null });
    },
    onSuccess: () => {
      setEditing(null);
      queryClient.invalidateQueries({ queryKey: ['campaigns'] });
      setNotice({ kind: 'success', title: 'Campaign draft updated' });
    },
    onError: (error: Error) => setNotice({ kind: 'error', title: 'Campaign could not be updated', message: apiError(error) }),
  });

  const remove = useMutation({
    mutationFn: async () => api.delete(`/campaigns/${deleting!.id}`),
    onSuccess: () => {
      setDeleting(null);
      queryClient.invalidateQueries({ queryKey: ['campaigns'] });
      setNotice({ kind: 'success', title: 'Campaign draft deleted' });
    },
    onError: (error: Error) => {
      setDeleting(null);
      setNotice({ kind: 'error', title: 'Campaign could not be deleted', message: apiError(error) });
    },
  });

  return (
    <div className="space-y-8">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p className="text-sm font-semibold text-brand-700">Campaign delivery</p>
          <h1 className="mt-1 text-3xl font-semibold tracking-tight">Campaigns</h1>
          <p className="mt-2 text-muted">Create a recipient snapshot, then launch only with a connected Meta account and an approved template.</p>
        </div>
        <button
          onClick={() => {
            setCreating(true);
            const first = approvedTemplates[0];
            if (first && !selectedTemplateId) {
              handleTemplateSelect(first.id);
            }
          }}
          className="flex items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-800 transition"
        >
          <Plus size={18} /> Create campaign
        </button>
      </div>

      {notice && <InlineNotice {...notice} onDismiss={() => setNotice(null)} />}

      {creating && (
        <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
          <div className="flex items-start justify-between">
            <div>
              <h2 className="text-xl font-semibold">Create campaign</h2>
              <p className="mt-1 text-sm text-muted">Only opted-in contacts are added to the audience snapshot.</p>
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
            <Field label="Campaign name">
              <input className="input" name="name" placeholder="Monsoon follow-up" required />
            </Field>

            <Field label="Approved Meta template">
              <select
                className="input"
                name="templateId"
                required
                value={selectedTemplateId}
                onChange={(e) => handleTemplateSelect(e.target.value)}
              >
                <option value="" disabled>
                  {approvedTemplates.length ? 'Select a template' : 'No approved templates available'}
                </option>
                {approvedTemplates.map((template) => (
                  <option key={template.id} value={template.id}>
                    {template.name} · {template.language}
                  </option>
                ))}
              </select>
            </Field>

            <Field label="Audience">
              <select
                className="input"
                name="audienceType"
                value={audienceType}
                onChange={(event) => setAudienceType(event.target.value as typeof audienceType)}
              >
                <option value="all_opted_in">All opted-in contacts ({optedIn.length})</option>
                <option value="groups">One or more saved groups</option>
                <option value="selected">Selected opted-in contacts</option>
              </select>
            </Field>

            <Field label="Schedule (optional, UTC)">
              <input className="input" type="datetime-local" name="scheduledAt" />
            </Field>

            {/* Variable Mapping Controls */}
            {selectedTemplate && selectedTemplate.variables && selectedTemplate.variables.length > 0 && (
              <div className="md:col-span-2 rounded-xl border border-line bg-slate-50/60 p-4">
                <div className="flex items-center gap-2">
                  <h3 className="text-sm font-semibold text-ink">Template Variable Mappings</h3>
                  <span className="rounded-md bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700">
                    {selectedTemplate.variables.length} variable{selectedTemplate.variables.length > 1 ? 's' : ''} detected
                  </span>
                </div>
                <p className="mt-1 text-xs text-muted">
                  Meta requires values for every variable placeholder in the template. Map each token to a contact field or static text.
                </p>
                <div className="mt-4 space-y-3">
                  {selectedTemplate.variables.map((token) => {
                    const current = variableConfig[token] || { type: 'contact_field', value: 'name', fallback: 'Customer' };
                    return (
                      <div key={token} className="flex flex-col sm:flex-row sm:items-center gap-3 rounded-lg border border-line bg-white p-3 shadow-xs">
                        <div className="flex items-center gap-2 w-28 shrink-0">
                          <span className="font-mono text-xs font-bold text-brand-700 bg-brand-50 px-2 py-1 rounded">
                            {`{{${token}}}`}
                          </span>
                        </div>
                        <select
                          aria-label={`Mapping type for variable ${token}`}
                          className="input text-xs py-1.5 h-9 sm:w-44"
                          value={current.type === 'contact_field' ? current.value : 'static'}
                          onChange={(e) => {
                            const val = e.target.value;
                            if (val === 'static') {
                              setVariableConfig((prev) => ({ ...prev, [token]: { type: 'static', value: '' } }));
                            } else {
                              setVariableConfig((prev) => ({
                                ...prev,
                                [token]: { type: 'contact_field', value: val, ...(val === 'name' ? { fallback: 'Customer' } : {}) },
                              }));
                            }
                          }}
                        >
                          <option value="name">Contact Name</option>
                          <option value="phone">Contact Phone</option>
                          <option value="static">Static Text</option>
                        </select>
                        {current.type === 'static' ? (
                          <input
                            aria-label={`Static value for variable ${token}`}
                            className="input text-xs py-1.5 h-9 flex-1"
                            placeholder="Enter static text..."
                            value={current.value}
                            onChange={(e) =>
                              setVariableConfig((prev) => ({
                                ...prev,
                                [token]: { type: 'static', value: e.target.value },
                              }))
                            }
                            required
                          />
                        ) : current.value === 'name' ? (
                          <input
                            aria-label={`Fallback for variable ${token}`}
                            className="input text-xs py-1.5 h-9 flex-1"
                            placeholder="Fallback if contact name is empty (e.g. Customer)"
                            value={current.fallback ?? ''}
                            onChange={(e) =>
                              setVariableConfig((prev) => {
                                const existing = prev[token] ?? { type: 'contact_field', value: 'name' };
                                return {
                                  ...prev,
                                  [token]: { ...existing, fallback: e.target.value },
                                };
                              })
                            }
                          />
                        ) : (
                          <div className="flex-1 text-xs text-muted flex items-center h-9">
                            Will use recipient&apos;s phone number
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>
              </div>
            )}

            {audienceType === 'groups' && (
              <AudienceList
                title="Choose groups"
                help="Contacts in the selected groups are combined, deduplicated, and checked for consent."
                empty="Create a group from the Contacts page first."
              >
                {groups.data?.map((group) => (
                  <label className="flex items-center gap-2 text-sm" key={group.id}>
                    <input name="groupIds" type="checkbox" value={group.id} />
                    <span>
                      {group.name} <span className="text-muted">{group.memberCount} contacts</span>
                    </span>
                  </label>
                ))}
              </AudienceList>
            )}

            {audienceType === 'selected' && (
              <AudienceList title="Selected audience" help="Choose the opted-in contacts for this one campaign." empty="No opted-in contacts are available.">
                {optedIn.map((contact) => (
                  <label className="flex items-center gap-2 text-sm" key={contact.id}>
                    <input name="contactIds" type="checkbox" value={contact.id} />
                    <span>
                      {contact.name || contact.phone} <span className="text-muted">{contact.phone}</span>
                    </span>
                  </label>
                ))}
              </AudienceList>
            )}

            <div className="md:col-span-2 flex gap-3">
              <button
                disabled={create.isPending || !approvedTemplates.length}
                className="rounded-xl bg-brand-700 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
              >
                {create.isPending ? 'Creating…' : 'Create campaign draft'}
              </button>
              <button type="button" onClick={() => setCreating(false)} className="rounded-xl border border-line px-4 py-3 text-sm font-semibold">
                Cancel
              </button>
            </div>
            {create.isError && <p className="md:col-span-2 text-sm text-red-700">{apiError(create.error)}</p>}
          </form>
        </section>
      )}

      {/* Campaigns Table */}
      <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-xs">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[960px] text-left text-sm">
            <thead className="bg-gray-50 text-xs uppercase tracking-wide text-muted">
              <tr>
                <th className="px-5 py-3">Campaign</th>
                <th className="px-5 py-3">Template</th>
                <th className="px-5 py-3">Recipients</th>
                <th className="px-5 py-3">Delivery Funnel</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {campaigns.data?.map((campaign) => (
                <tr key={campaign.id} className="hover:bg-gray-50/50 transition-colors">
                  <td className="px-5 py-4">
                    <p className="font-medium text-ink">{campaign.name}</p>
                    <p className="text-xs text-muted">
                      {campaign.scheduledAt ? `Scheduled: ${campaign.scheduledAt}` : campaign.launchedAt ? `Launched: ${campaign.launchedAt}` : 'Draft snapshot'}
                    </p>
                    {campaign.failureMessage && (
                      <p className="mt-2 max-w-md text-xs text-red-700 font-medium" role="alert">
                        {campaign.failureMessage}
                        {campaign.failureCode ? ` (${campaign.failureCode})` : ''}
                      </p>
                    )}
                  </td>
                  <td className="px-5 py-4">
                    <span className="font-medium text-ink">{campaign.templateName}</span>
                    <p className="text-xs text-muted">{campaign.templateLanguage}</p>
                  </td>
                  <td className="px-5 py-4 font-medium">{campaign.recipientCount}</td>
                  <td className="px-5 py-4">
                    <div className="space-y-1">
                      <div className="flex items-center gap-1.5 text-xs">
                        <span className="font-semibold text-ink">{campaign.deliveredCount}</span>
                        <span className="text-muted">delivered</span>
                        <span className="text-muted">·</span>
                        <span className="font-semibold text-brand-700">{campaign.readCount}</span>
                        <span className="text-muted">read</span>
                      </div>
                      <div className="text-xs">
                        {campaign.status === 'dispatched' ? (
                          <span className="text-amber-700 font-medium">Awaiting delivery reports ({campaign.acceptedCount} accepted)</span>
                        ) : (
                          <span className="text-muted">{campaign.acceptedCount} accepted by Meta</span>
                        )}
                      </div>
                      {campaign.failedCount > 0 && (
                        <p className="text-xs font-semibold text-red-700">{campaign.failedCount} failed</p>
                      )}
                    </div>
                  </td>
                  <td className="px-5 py-4">
                    <Status value={campaign.status} />
                  </td>
                  <td className="px-5 py-4">
                    <div className="flex justify-end items-center gap-1">
                      {campaign.recipientCount > 0 && (
                        <button
                          aria-label={`View recipients for ${campaign.name}`}
                          title="View recipients delivery log"
                          onClick={() => setInspectingCampaign(campaign)}
                          className="rounded-lg p-2 text-muted hover:bg-gray-100 hover:text-ink transition"
                        >
                          <Users size={16} />
                        </button>
                      )}
                      {campaign.status === 'draft' ? (
                        <>
                          <button
                            aria-label={`Edit ${campaign.name}`}
                            onClick={() => setEditing(campaign)}
                            className="rounded-lg p-2 text-brand-700 hover:bg-brand-50"
                          >
                            <Pencil size={16} />
                          </button>
                          <button
                            aria-label={`Delete ${campaign.name}`}
                            onClick={() => setDeleting(campaign)}
                            className="rounded-lg p-2 text-red-700 hover:bg-red-50"
                          >
                            <Trash2 size={16} />
                          </button>
                          <button
                            disabled={launch.isPending}
                            onClick={() => setLaunchingCampaign(campaign)}
                            className="inline-flex items-center gap-1 rounded-lg bg-brand-700 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-800 disabled:opacity-50 transition"
                          >
                            <Send size={14} /> Launch
                          </button>
                        </>
                      ) : (
                        <button
                          onClick={() => setInspectingCampaign(campaign)}
                          className="text-xs font-semibold text-brand-700 hover:underline px-2 py-1"
                        >
                          Inspect
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {campaigns.isLoading && <p className="p-8 text-center text-muted">Loading campaigns…</p>}
        {!campaigns.isLoading && !campaigns.data?.length && (
          <div className="p-12 text-center">
            <Megaphone className="mx-auto text-brand-700" />
            <p className="mt-3 font-semibold">No campaigns yet</p>
            <p className="mt-1 text-sm text-muted">Import opted-in contacts and select an approved Meta template to get started.</p>
          </div>
        )}
      </section>

      {/* Edit Campaign Modal */}
      {editing && (
        <div className="fixed inset-0 z-40 grid place-items-center bg-slate-950/35 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
          <form
            className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl"
            onSubmit={(event) => {
              event.preventDefault();
              update.mutate(event.currentTarget);
            }}
          >
            <h2 className="text-xl font-semibold">Edit campaign draft</h2>
            <div className="mt-5 grid gap-4">
              <Field label="Campaign name">
                <input className="input" name="name" defaultValue={editing.name} required />
              </Field>
              <Field label="Schedule (optional, UTC)">
                <input className="input" type="datetime-local" name="scheduledAt" defaultValue={editing.scheduledAt?.slice(0, 16) ?? ''} />
              </Field>
            </div>
            <div className="mt-6 flex justify-end gap-3">
              <button type="button" onClick={() => setEditing(null)} className="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold">
                Cancel
              </button>
              <button
                disabled={update.isPending}
                className="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
              >
                {update.isPending ? 'Saving…' : 'Save changes'}
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Delete Confirmation Modal */}
      {deleting && (
        <ConfirmPanel
          title="Delete campaign draft?"
          message={`“${deleting.name}” and its recipient snapshot will be permanently removed. Launched campaign history is always preserved.`}
          pending={remove.isPending}
          onCancel={() => setDeleting(null)}
          onConfirm={() => remove.mutate()}
        />
      )}

      {/* Launch Pre-Flight Confirmation Modal */}
      {launchingCampaign && (
        <div className="fixed inset-0 z-40 grid place-items-center bg-slate-950/40 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
          <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl space-y-4">
            <div className="flex items-start justify-between">
              <div>
                <h2 className="text-lg font-bold text-ink">Ready to Launch Campaign?</h2>
                <p className="mt-0.5 text-xs text-muted">Verify audience and template specifications before dispatching.</p>
              </div>
              <button aria-label="Close modal" onClick={() => setLaunchingCampaign(null)} className="rounded-lg p-1.5 hover:bg-gray-100">
                <X size={18} />
              </button>
            </div>

            <div className="rounded-xl border border-line bg-gray-50 p-4 space-y-2 text-xs">
              <div className="flex justify-between">
                <span className="text-muted">Campaign Name:</span>
                <span className="font-semibold text-ink">{launchingCampaign.name}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Template:</span>
                <span className="font-semibold text-ink">{launchingCampaign.templateName} ({launchingCampaign.templateLanguage})</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Recipient Audience:</span>
                <span className="font-semibold text-brand-700">{launchingCampaign.recipientCount} contact(s)</span>
              </div>
            </div>

            <div className="flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800 leading-relaxed">
              <AlertCircle size={16} className="shrink-0 mt-0.5" />
              <p>
                <strong>Opt-out Suppression Active:</strong> Any contacts who opted out after audience snapshot creation will be skipped at send time.
              </p>
            </div>

            <div className="flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setLaunchingCampaign(null)}
                className="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold hover:bg-gray-50"
              >
                Cancel
              </button>
              <button
                disabled={launch.isPending}
                onClick={() => {
                  const id = launchingCampaign.id;
                  setLaunchingCampaign(null);
                  launch.mutate(id);
                }}
                className="rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-50"
              >
                {launch.isPending ? 'Queuing…' : 'Confirm & Launch'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Campaign Recipients Inspection Drawer / Modal */}
      {inspectingCampaign && (
        <div className="fixed inset-0 z-40 grid place-items-center bg-slate-950/40 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
          <div className="w-full max-w-4xl max-h-[85vh] flex flex-col rounded-2xl bg-white shadow-2xl overflow-hidden">
            <div className="flex items-center justify-between border-b border-line px-6 py-4 bg-gray-50/70">
              <div>
                <h2 className="text-lg font-bold text-ink">Campaign Recipients: {inspectingCampaign.name}</h2>
                <div className="flex items-center gap-3 mt-1 text-xs text-muted">
                  <span>{inspectingCampaign.recipientCount} total</span>
                  <span>·</span>
                  <span>{inspectingCampaign.acceptedCount} accepted</span>
                  <span>·</span>
                  <span className="text-brand-700 font-semibold">{inspectingCampaign.deliveredCount} delivered</span>
                  <span>·</span>
                  <span className="text-emerald-700 font-semibold">{inspectingCampaign.readCount} read</span>
                  {inspectingCampaign.failedCount > 0 && (
                    <>
                      <span>·</span>
                      <span className="text-red-700 font-semibold">{inspectingCampaign.failedCount} failed</span>
                    </>
                  )}
                </div>
              </div>
              <button aria-label="Close recipient modal" onClick={() => setInspectingCampaign(null)} className="rounded-lg p-2 hover:bg-gray-200/60">
                <X size={18} />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-6">
              {recipientsQuery.isLoading ? (
                <p className="p-8 text-center text-muted text-sm">Loading recipient delivery details…</p>
              ) : !recipientsQuery.data?.length ? (
                <p className="p-8 text-center text-muted text-sm">No recipient details found for this campaign.</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs">
                    <thead className="bg-gray-50 uppercase tracking-wider text-muted border-b border-line">
                      <tr>
                        <th className="px-4 py-2.5">Contact</th>
                        <th className="px-4 py-2.5">Status</th>
                        <th className="px-4 py-2.5">Meta Message ID</th>
                        <th className="px-4 py-2.5">Delivered / Read</th>
                        <th className="px-4 py-2.5">Diagnostics</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-line">
                      {recipientsQuery.data.map((r) => (
                        <tr key={r.id} className="hover:bg-gray-50/50">
                          <td className="px-4 py-3">
                            <p className="font-semibold text-ink">{r.contactName || '—'}</p>
                            <p className="text-muted font-mono">{r.phone}</p>
                          </td>
                          <td className="px-4 py-3">
                            <RecipientStatus value={r.status} />
                          </td>
                          <td className="px-4 py-3 font-mono text-muted max-w-[140px] truncate" title={r.metaMessageId || ''}>
                            {r.metaMessageId || '—'}
                          </td>
                          <td className="px-4 py-3 text-muted space-y-0.5">
                            <div>Delivered: {r.deliveredAt ? new Date(r.deliveredAt).toLocaleTimeString() : '—'}</div>
                            <div>Read: {r.readAt ? new Date(r.readAt).toLocaleTimeString() : '—'}</div>
                          </td>
                          <td className="px-4 py-3">
                            {r.failureMessage ? (
                              <div className="rounded bg-red-50 p-2 text-red-700">
                                <span className="font-bold">{r.failureCode}: </span>
                                <span>{r.failureMessage}</span>
                              </div>
                            ) : (
                              <span className="text-muted">—</span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            <div className="border-t border-line px-6 py-3 bg-gray-50 flex justify-end">
              <button
                type="button"
                onClick={() => setInspectingCampaign(null)}
                className="rounded-xl border border-line bg-white px-4 py-2 text-xs font-semibold hover:bg-gray-50"
              >
                Close
              </button>
            </div>
          </div>
        </div>
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

function AudienceList({ title, help, empty, children }: { title: string; help: string; empty: string; children: React.ReactNode }) {
  const items = Array.isArray(children) ? children.filter(Boolean) : children ? [children] : [];
  return (
    <div className="md:col-span-2 rounded-xl border border-line p-4">
      <p className="text-sm font-semibold">{title}</p>
      <p className="mt-1 text-xs text-muted">{help}</p>
      <div className="mt-3 max-h-40 space-y-2 overflow-auto">{items.length ? children : <p className="text-sm text-muted">{empty}</p>}</div>
    </div>
  );
}

function Status({ value }: { value: string }) {
  const styles: Record<string, string> = {
    draft: 'bg-gray-100 text-muted',
    queued: 'bg-blue-50 text-blue-700',
    dispatched: 'bg-amber-50 text-amber-800 border border-amber-200',
    completed: 'bg-brand-50 text-brand-700',
    failed: 'bg-red-50 text-red-700',
  };
  return (
    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${styles[value] ?? 'bg-gray-100 text-muted'}`}>
      {value}
    </span>
  );
}

function RecipientStatus({ value }: { value: string }) {
  const styles: Record<string, string> = {
    queued: 'bg-gray-100 text-muted',
    accepted: 'bg-blue-50 text-blue-700',
    sent: 'bg-indigo-50 text-indigo-700',
    delivered: 'bg-brand-50 text-brand-700',
    read: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-red-50 text-red-700',
  };
  return (
    <span className={`rounded-full px-2 py-0.5 text-xs font-semibold capitalize ${styles[value] ?? 'bg-gray-100 text-muted'}`}>
      {value}
    </span>
  );
}

function apiError(error: Error): string {
  const candidate = error as Error & { response?: { data?: { error?: { message?: string } } } };
  return candidate.response?.data?.error?.message ?? 'The campaign could not be completed.';
}
