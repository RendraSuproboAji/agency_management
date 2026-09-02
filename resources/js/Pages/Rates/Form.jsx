import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, ButtonLink, Field, inputClass } from '@/Components/ui';

export default function Form({ rate, serviceTypes, units, unitLabels }) {
    const editing = Boolean(rate.id);

    const { data, setData, post, put, processing, errors } = useForm({
        service_type: rate.service_type ?? 'gaussian_splatting',
        unit: rate.unit ?? 'sqm',
        label: rate.label ?? '',
        unit_price: rate.unit_price ?? '',
        min_charge: rate.min_charge ?? '',
        active: rate.active ?? true,
    });

    return (
        <AppLayout title={editing ? 'Ubah tarif' : 'Tambah tarif'}>
            <h1 className="mb-4 text-2xl font-semibold">{editing ? 'Ubah tarif' : 'Tambah tarif'}</h1>

            <form onSubmit={(e) => { e.preventDefault(); editing ? put(`/rates/${rate.id}`) : post('/rates'); }}>
                <div className="gap-x-4 sm:grid sm:grid-cols-2">
                    <Field label="Jenis layanan *" error={errors.service_type}>
                        <select className={inputClass} value={data.service_type} onChange={(e) => setData('service_type', e.target.value)}>
                            {serviceTypes.map((type) => (
                                <option key={type} value={type}>{type.replace(/_/g, ' ')}</option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Satuan *" error={errors.unit}>
                        <select className={inputClass} value={data.unit} onChange={(e) => setData('unit', e.target.value)}>
                            {units.map((unit) => <option key={unit} value={unit}>{unitLabels[unit]}</option>)}
                        </select>
                    </Field>
                    <Field label="Keterangan baris *" error={errors.label} wide>
                        <input className={inputClass} required placeholder="Pemindaian area dan pengolahan splat"
                               value={data.label} onChange={(e) => setData('label', e.target.value)} />
                    </Field>
                    <Field label="Harga satuan (Rp) *" error={errors.unit_price}>
                        <input type="number" step="0.01" min="0" className={inputClass} required
                               value={data.unit_price} onChange={(e) => setData('unit_price', e.target.value)} />
                    </Field>
                    <Field label="Biaya minimum (Rp)" error={errors.min_charge}>
                        <input type="number" step="0.01" min="0" className={inputClass}
                               value={data.min_charge ?? ''} onChange={(e) => setData('min_charge', e.target.value)} />
                    </Field>
                </div>

                <label className="mb-3 flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={data.active} onChange={(e) => setData('active', e.target.checked)} />
                    Aktif — ikut dipakai kalkulator penawaran
                </label>

                <div className="flex gap-2">
                    <Button type="submit" variant="primary" disabled={processing}>Simpan</Button>
                    <ButtonLink href="/rates" variant="ghost">Batal</ButtonLink>
                </div>
            </form>
        </AppLayout>
    );
}
