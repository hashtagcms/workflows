module.exports = {
  testEnvironment: 'node',
  roots: ['<rootDir>/tests/js', '<rootDir>/resources/js'],
  transform: {
    '^.+\\.js$': 'babel-jest',
  },
  testMatch: ['**/tests/js/**/*.spec.js'],
  modulePathIgnorePatterns: ['<rootDir>/vendor/', '<rootDir>/node_modules/'],
};
