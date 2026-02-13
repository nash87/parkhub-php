import React from 'react';
import { Link } from 'react-router-dom';
import { Confetti, ArrowRight } from '@phosphor-icons/react';

export default function Welcome() {
  return (
    <div className="text-center py-12">
      <Confetti size={64} className="text-amber-600 mx-auto mb-4" />
      <h1 className="text-3xl font-bold mb-2">Welcome to ParkHub!</h1>
      <p className="text-slate-500 mb-8">Your parking management system is ready.</p>
      <Link to="/book" className="btn-primary inline-flex items-center gap-2">Book Your First Spot <ArrowRight size={18} /></Link>
    </div>
  );
}
