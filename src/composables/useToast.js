import { reactive } from 'vue';

const toast = reactive({
  show: false,
  type: 'success',
  message: ''
});

let hideTimer = null;

export function useToast() {
  const showToast = (message, type = 'success', duration = 3500) => {
    toast.message = message;
    toast.type = type;
    toast.show = true;
    if (hideTimer) clearTimeout(hideTimer);
    hideTimer = setTimeout(() => {
      toast.show = false;
    }, duration);
  };

  const hideToast = () => {
    toast.show = false;
    if (hideTimer) clearTimeout(hideTimer);
  };

  return { toast, showToast, hideToast };
}
