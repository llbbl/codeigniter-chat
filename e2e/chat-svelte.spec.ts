import { chatScenario } from './support/chat-scenarios';

chatScenario({
  name: 'Svelte chat',
  path: '/chat/svelte',
  messageInput: '#message-input',
  submitButton: 'button[type="submit"]',
});
