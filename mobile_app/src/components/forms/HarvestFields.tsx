import { useTranslation } from 'react-i18next';
import FormField from './FormField';

interface Props {
  quantity: string; onQuantityChange: (v: string) => void;
}

const HarvestFields = ({ quantity, onQuantityChange }: Props) => {
  const { t } = useTranslation();
  return (
    <FormField label={t('form.quantityHarvested')} suffix="kg">
      <input type="number" inputMode="decimal" step="0.001" min="0" required
        value={quantity} onChange={(e) => onQuantityChange(e.target.value)}
        className="cl-input h-12 rounded-xl text-base flex-1" placeholder="0" />
    </FormField>
  );
};

export default HarvestFields;

