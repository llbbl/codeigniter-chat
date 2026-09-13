import { chatScenario } from './support/chat-scenarios';

chatScenario({
  name: 'HTML chat',
  path: '/chat/html',
  messageInput: '#content',
  submitButton: 'input[type="submit"]',
  authorInput: '#name',
});
