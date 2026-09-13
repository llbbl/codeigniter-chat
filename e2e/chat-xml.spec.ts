import { chatScenario } from './support/chat-scenarios';

chatScenario({
  name: 'XML chat',
  path: '/chat',
  messageInput: '#content',
  submitButton: 'input[type="submit"]',
  network: { path: '/chat/backend', format: 'xml' },
});
