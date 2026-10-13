import Alpine from 'alpinejs';
import { adminForm, adminUpload } from './admin-form';
import playerGuard from './player-guard';
import razorpayCheckout from './razorpay-checkout';
import theme from './theme';
import videoProgress from './video-progress';

window.Alpine = Alpine;

Alpine.store('theme', theme);

Alpine.data('adminForm', adminForm);
Alpine.data('adminUpload', adminUpload);
Alpine.data('playerGuard', playerGuard);
Alpine.data('razorpayCheckout', razorpayCheckout);
Alpine.data('videoProgress', videoProgress);

Alpine.start();
