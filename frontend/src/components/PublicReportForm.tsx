import React, { useEffect, useState } from 'react';
import { defmanApi } from '../api/client';
import {
  DefectPriority,
  DefectSeverity,
  PersonType,
  type Product,
} from '../types/defman';

/**
 * <PublicReportForm />
 * Refactored from: form_raportpub.php (+ helpreportpub.html inline modal)
 * Endpoint: POST /api/defects/public
 */
export const PublicReportForm: React.FC<{ onSuccess?: () => void }> = ({
  onSuccess,
}) => {
  const [products, setProducts] = useState<Product[]>([]);
  const [showHelp, setShowHelp] = useState(false);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const [form, setForm] = useState({
    who: '',
    contact_email: '',
    product: '',
    title: '',
    description: '',
    discovered_by: '',
    persontype: PersonType.CUSTOMER,
    location: '',
    occured: new Date().toISOString().slice(0, 10),
    environment: '',
    severity: DefectSeverity.MINOR,
    priority: DefectPriority.MOST_URGENT,
    addinfo: '',
  });

  useEffect(() => {
    defmanApi.getProducts().then((res) => {
      setProducts(res.products);
      if (res.products.length > 0) {
        setForm((prev) => ({ ...prev, product: res.products[0].name }));
      }
    });
  }, []);

  const clearForm = () => {
    setForm((prev) => ({
      ...prev,
      who: '',
      contact_email: '',
      title: '',
      description: '',
      discovered_by: '',
      location: '',
      occured: new Date().toISOString().slice(0, 10),
      environment: '',
      addinfo: '',
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);
    setStatusMessage(null);
    try {
      const res = await defmanApi.submitPublicDefect(form);
      setStatusMessage(res.message);
      clearForm();
      onSuccess?.();
    } catch (err) {
      setErrorMessage(err instanceof Error ? err.message : 'Submission failed');
    }
  };

  return (
    <div className="bg-slate-900 border border-slate-700 rounded-xl p-6 max-w-2xl mx-auto text-slate-100 shadow-lg">
      <div className="flex items-center justify-between border-b border-slate-700 pb-3 mb-4">
        <div>
          <h2 className="text-lg font-bold text-amber-400">
            New Public Defect Report (Level 1)
          </h2>
          <p className="text-xs text-slate-400">
            Fields marked with (<span className="text-red-400">*</span>) are required.
          </p>
        </div>
        <button
          type="button"
          onClick={() => setShowHelp((s) => !s)}
          className="text-xs px-3 py-1 rounded bg-slate-800 hover:bg-slate-700 text-amber-300 border border-slate-600"
        >
          {showHelp ? 'Hide Help' : 'Help (helpreportpub.html)'}
        </button>
      </div>

      {showHelp && (
        <div className="mb-4 p-4 rounded-lg bg-slate-950 border border-amber-500/40 text-xs space-y-1">
          <div className="font-semibold text-amber-300 mb-1">
            How to fill out the public defect report form (Example):
          </div>
          <div><strong>Who reported:</strong> Jason</div>
          <div><strong>Contact Email:</strong> jason@email.com</div>
          <div><strong>Title:</strong> PopUp Menu Crash</div>
          <div><strong>Description:</strong> When adding entries into the popup menu in a certain way, the application will close without any error messages.</div>
          <div><strong>Location:</strong> settings form | <strong>Occurred:</strong> 2004-04-14 | <strong>Environment:</strong> AMD Athlon XP 2000, WinXP</div>
        </div>
      )}

      {errorMessage && (
        <div className="mb-4 p-3 rounded bg-red-950/80 border border-red-600 text-amber-300 text-sm">
          {errorMessage}
        </div>
      )}
      {statusMessage && (
        <div className="mb-4 p-3 rounded bg-emerald-950/80 border border-emerald-600 text-emerald-300 text-sm">
          {statusMessage}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-3 text-sm">
        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="text-slate-300">
              Who reported the problem <span className="text-red-400">*</span>
            </span>
            <input
              type="text"
              maxLength={6}
              required
              value={form.who}
              onChange={(e) => setForm({ ...form, who: e.target.value })}
              placeholder="Max 6 chars (e.g. Jason)"
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">
              Contact Email <span className="text-red-400">*</span>
            </span>
            <input
              type="email"
              required
              value={form.contact_email}
              onChange={(e) => setForm({ ...form, contact_email: e.target.value })}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
        </div>

        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="text-slate-300">
              Product and release <span className="text-red-400">*</span>
            </span>
            <select
              value={form.product}
              onChange={(e) => setForm({ ...form, product: e.target.value })}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            >
              {products.map((p) => (
                <option key={p.id} value={p.name}>
                  {p.name}
                </option>
              ))}
            </select>
          </label>
          <label className="block">
            <span className="text-slate-300">
              Date Problem occurred <span className="text-red-400">*</span>
            </span>
            <input
              type="date"
              required
              value={form.occured}
              onChange={(e) => setForm({ ...form, occured: e.target.value })}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
        </div>

        <label className="block">
          <span className="text-slate-300">
            Title <span className="text-red-400">*</span>
          </span>
          <input
            type="text"
            required
            value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
          />
        </label>

        <label className="block">
          <span className="text-slate-300">
            Description of the problem <span className="text-red-400">*</span>
          </span>
          <textarea
            rows={4}
            required
            value={form.description}
            onChange={(e) => setForm({ ...form, description: e.target.value })}
            className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
          />
        </label>

        <div className="grid grid-cols-3 gap-3">
          <label className="block">
            <span className="text-slate-300">Who discovered defect</span>
            <input
              type="text"
              value={form.discovered_by}
              onChange={(e) => setForm({ ...form, discovered_by: e.target.value })}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">
              Person type <span className="text-red-400">*</span>
            </span>
            <select
              value={form.persontype}
              onChange={(e) =>
                setForm({ ...form, persontype: e.target.value as PersonType })
              }
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            >
              {Object.values(PersonType).map((pt) => (
                <option key={pt} value={pt}>
                  {pt}
                </option>
              ))}
            </select>
          </label>
          <label className="block">
            <span className="text-slate-300">Location found</span>
            <input
              type="text"
              value={form.location}
              onChange={(e) => setForm({ ...form, location: e.target.value })}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
        </div>

        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="text-slate-300">
              Severity <span className="text-red-400">*</span>
            </span>
            <select
              value={form.severity}
              onChange={(e) =>
                setForm({ ...form, severity: e.target.value as DefectSeverity })
              }
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            >
              <option value={DefectSeverity.MINOR}>minor</option>
              <option value={DefectSeverity.MAJOR}>major</option>
              <option value={DefectSeverity.CRITICAL}>critical</option>
            </select>
          </label>
          <label className="block">
            <span className="text-slate-300">
              Priority <span className="text-red-400">*</span>
            </span>
            <select
              value={form.priority}
              onChange={(e) =>
                setForm({ ...form, priority: e.target.value as DefectPriority })
              }
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            >
              <option value={DefectPriority.MOST_URGENT}>most urgent</option>
              <option value={DefectPriority.AVERAGE}>average</option>
              <option value={DefectPriority.LESS_URGENT}>less urgent</option>
            </select>
          </label>
        </div>

        <label className="block">
          <span className="text-slate-300">Technical environment</span>
          <textarea
            rows={2}
            value={form.environment}
            onChange={(e) => setForm({ ...form, environment: e.target.value })}
            className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
          />
        </label>

        <label className="block">
          <span className="text-slate-300">Additional information</span>
          <textarea
            rows={2}
            value={form.addinfo}
            onChange={(e) => setForm({ ...form, addinfo: e.target.value })}
            className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
          />
        </label>

        <div className="flex items-center gap-3 pt-2">
          <button
            type="submit"
            className="px-4 py-2 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
          >
            Submit Report
          </button>
          <button
            type="button"
            onClick={clearForm}
            className="px-4 py-2 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-600"
          >
            Clear
          </button>
        </div>
      </form>
    </div>
  );
};
