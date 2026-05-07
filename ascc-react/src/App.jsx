import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import Login from './Login';

function App() {
  return (
    <Router>
      <Routes>
        <Route path="/" element={
          <div style={{ textAlign: 'center', padding: '50px' }}>
            <h1>ASCC - Frontend Migrado a React</h1>
            <p>Bienvenido al nuevo Frontend separado.</p>
          </div>
        } />
        <Route path="/login" element={<Login />} />
      </Routes>
    </Router>
  );
}

export default App;

