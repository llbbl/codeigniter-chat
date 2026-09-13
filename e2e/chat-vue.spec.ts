import { chatScenario } from './support/chat-scenarios';

chatScenario({
  name: 'Vue chat',
  path: '/chat/vue',
  messageInput: '#message-input',
  submitButton: 'button[type="submit"]',
});
