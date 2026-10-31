import React, { useCallback, useEffect, useState } from 'react';
import { defmanApi } from '../api/client';
import type { Product } from '../types/defman';

/**
 * <ProductManager />
 * Refactored from: form_productdetails.php
 * Endpoints: GET/POST/DELETE /api/admin/products
 */
export const ProductManager: React.FC = () => {
  const [products, setProducts] = useState<Product[]>([]);
  const [name, setName] = useState('');
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const loadProducts = useCallback(async () => {
    const res = await defmanApi.getProducts();
    setProducts(res.products);
  }, []);

  useEffect(() => {
    loadProducts();
  }, [loadProducts]);

  const handleAdd = async (e: React.FormEvent) => {
    e.preventDefault();
    setMessage(null);
    setError(null);
    try {
      const res = await defmanApi.createProduct(name);
      setMessage(res.message);
      setName('');
      loadProducts();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create product');
    }
  };

  const handleDelete = async (p: Product) => {
    if (!window.confirm(`Are you SURE that you want to DELETE\nProduct: ${p.name} ?`)) {
      return;
    }
    setMessage(null);
    setError(null);
    try {
      const res = await defmanApi.deleteProduct(p.id);
      setMessage(res.message);
      loadProducts();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to delete product');
    }
  };

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 text-slate-100">
      <div className="bg-slate-900 border border-slate-700 rounded-xl p-5 shadow-lg space-y-3">
        <h2 className="text-lg font-bold text-amber-400">
          All Products ({products.length})
        </h2>
        {error && (
          <div className="p-2.5 rounded bg-red-950 border border-red-600 text-amber-300 text-xs">
            {error}
          </div>
        )}
        {message && (
          <div className="p-2.5 rounded bg-emerald-950 border border-emerald-600 text-emerald-300 text-xs">
            {message}
          </div>
        )}
        <table className="w-full text-left text-xs border-collapse">
          <thead>
            <tr className="bg-slate-800 text-slate-300 border-b border-slate-700">
              <th className="p-2.5">ID</th>
              <th className="p-2.5">Product Name</th>
              <th className="p-2.5">Reports</th>
              <th className="p-2.5 text-right">Delete</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-800">
            {products.map((p) => (
              <tr key={p.id}>
                <td className="p-2.5 font-mono">#{p.id}</td>
                <td className="p-2.5 font-medium">{p.name}</td>
                <td className="p-2.5 font-mono text-slate-400">
                  {p.report_count ?? 0}
                </td>
                <td className="p-2.5 text-right">
                  <button
                    type="button"
                    onClick={() => handleDelete(p)}
                    className="px-2.5 py-1 rounded bg-red-900/70 hover:bg-red-800 text-red-200"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <form
        onSubmit={handleAdd}
        className="bg-slate-900 border border-slate-700 rounded-xl p-5 shadow-lg space-y-3 h-fit text-xs"
      >
        <h3 className="text-lg font-bold text-amber-400">Add New Product</h3>
        <label className="block">
          <span className="text-slate-300">Product name (max 100 chars) *</span>
          <input
            type="text"
            maxLength={100}
            required
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="e.g. MMF 2.0"
            className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
          />
        </label>
        <div className="flex gap-2">
          <button
            type="submit"
            className="px-4 py-2 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
          >
            Add product
          </button>
          <button
            type="button"
            onClick={() => setName('')}
            className="px-4 py-2 rounded bg-slate-800 hover:bg-slate-700 text-slate-300"
          >
            Clear
          </button>
        </div>
      </form>
    </div>
  );
};
